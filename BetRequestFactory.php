<?php

namespace Modules\Platform\Services\Bet;

use App\User;
use Illuminate\Container\RewindableGenerator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Modules\DataHarvester\DTO\BookMaker\BookmakerInterface;
use Modules\DataHarvester\DTO\BookMaker\Pinnacle;
use Modules\DataHarvester\DTO\Line;
use Modules\DataHarvester\Entities\Bet;
use Modules\DataHarvester\Entities\Event;
use Modules\DataHarvester\Entities\LineCache;
use Modules\DataHarvester\Services\Line\LineManager;
use Modules\Platform\Contracts\OddsFormatter;
use Modules\Platform\Entities\Bet\BetRequest;
use Modules\Platform\Exceptions\BelowMinAmountException;
use Modules\Platform\Services\TransactionProcessor;
use Ramsey\Uuid\Uuid;

/**
 * Class BetRequestFactory
 * @package Modules\Platform\Services\Bet
 * @deprecated
 */
class BetRequestFactory
{
    /**
     * @var LineManager
     */
    private $lineManager;

    /**
     * @var BookmakerInterface[]|array
     */
    private $bookmakers;

    public function __construct(LineManager $lineManager, RewindableGenerator $bookmakers)
    {
        $this->lineManager = $lineManager;
        $this->bookmakers = $bookmakers;
    }

    /**
     * @param LineCache $cachedLine
     * @param array $params
     * @return BetRequest
     * @throws BelowMinAmountException
     */
    public function createBetRequest(LineCache $cachedLine, User $user, array $params): BetRequest
    {
        $event = $cachedLine->getEvent();

        if($this->lineManager->getMinRisk($cachedLine)['amount'] > $params['amount']){
            throw new BelowMinAmountException(__('Below minimum bet amount'));
        }

        $oddsBase = $this->lineManager->getPriceForUser($user, $cachedLine, false);
        $oddsUser = OddsFormatter::unFormat(\auth()->user() ?? $user, $params['value']);
        if(\auth()->user() && \auth()->user()->getOddsFormat() === OddsFormatter::ODDS_FORMAT_AMERICAN){
            if($this->preventAmericanAutobet($params['value'], $oddsBase)){
                $oddsUser = $oddsBase;
            }
        }
        if($oddsUser < $oddsBase){
            $oddsUser = $oddsBase;
        }
        $betRequest = new BetRequest();
        $betRequest->setUuid(Uuid::uuid4());
        $betRequest->setEvent($event);
        $betRequest->setOddsBase($oddsBase);
        $betRequest->setOddsUser($oddsUser);
        $betRequest->setAmountUser($params['amount']);
        $betRequest->setUser($user);

        $betRequest->setAmountMin($this->lineManager->getMinRisk($cachedLine)['amount']);
        $betRequest->setAmountMax($this->lineManager->getMaxRisk($cachedLine));
        if(isset($params['ttl'])){
            $betRequest->setTTL($params['ttl'] * 3600 ?? null);
        }

        $betRequest->setAmountSystem(0);
        list($amountSystem, $keepAdmin, $keepPartner) = $this->calculateAmountSystem(
            $betRequest,
            $cachedLine,
            $user,
            $params['amount'],
            $this->lineManager->getMinRisk($cachedLine)
        );
        $betRequest->setAmountSystem($amountSystem);

        foreach ($this->bookmakers as $bookmaker){
                $bookmakerData = $cachedLine->getBookmakerData($bookmaker);
                if($bookmakerData){
                    $betRequest->setParams(
                        $bookmaker->getName(),
                        [
                            'line_id' => $cachedLine->{'get'.$bookmaker->getName().'Id'}(),
                            'alt_line_id' => $bookmakerData['alt_line_id'] ?? null,
                            'period_id' => $bookmakerData['period_id'],
                            'team' => $bookmakerData['team'] ?? null,
                            'side' => $bookmakerData['side'] ?? null,
                            'value' => $bookmakerData['value'] ?? null,
                            'type' => $bookmakerData['type'] ?? null,
                            'minAmount' => $bookmakerData['amount_min'],
                            'maxAmount' => $bookmakerData['amount_max'],
                            'price' => $bookmakerData['price'],
                            'event_id' => $event->{'get'.$bookmaker->getName().'Id'}(),
                            'league_id' => $event->league->{'get'.$bookmaker->getName().'Id'}(),
                            'sport_id' => $event->sport->{'get'.$bookmaker->getName().'Id'}(),
                        ]
                    );
                    $betRequest->setParams(null, ['percent_keep_admin' => 100 - $user->getBetPercent()]);
                    $betRequest->setParams(null, ['percent_keep_partner' => (float) $user->getPartnerBetPercent()]);
                    $betRequest->setParams(null, ['admin_fee' => $user->getAdminBettingFee()]);
                    $betRequest->setParams(null, ['partner_fee' => $user->getPartnerBettingFee()]);
                    $betRequest->setParams(null, ['keep_admin' => $keepAdmin]);
                    $betRequest->setParams(null, ['keep_partner' => $keepPartner]);
                }
            }

        return $betRequest;
    }

    /**
     * @param int $americanOdds
     * @param float $oddsBase
     * @return float|null
     */
    private function preventAmericanAutobet(int $americanOdds, float $oddsBase): ?float
    {
        $user = \auth()->user();
        if(OddsFormatter::format($user, $oddsBase) >= $americanOdds){
            return true;
        }
        return false;
    }

    /**
     * @param User $user
     * @param float $amountUser
     * @param array $amountMin
     * @return float
     */
    private function calculateAmountSystem(BetRequest $betRequest, LineCache $cachedLine, User $user, float $amountUser, array $amountMin): array
    {
//        $availableForLine = $this->availableReserveForLine($cachedLine, $user);

        $percentToBookmaker = $user->getBetPercent();
        $percentToAdmin = 100 - $percentToBookmaker;

        $percentToPartner = $user->getPartnerBetPercent();

        list ($rounded, $amountToAdmin, $amountToPartner) = $this->calculateKeepAmounts($amountUser, $percentToAdmin, $percentToPartner, $amountMin);

        if($amountUser <= $amountMin['amount']){
            return [$amountUser, 0, 0];
        }

        return [$rounded, $amountToAdmin, $amountToPartner];
    }

    private function calculateKeepAmounts(float $amountUser, float $percentToAdmin, float $percentToPartner, array $amountMin): array
    {
        $amountToAdmin = 0;
        $amountToPartner = 0;
        /** @var float $calculatedAmount | How much we really send to bookmaker */
        $calculatedAmount = $amountUser - $amountUser * ($percentToAdmin + $percentToPartner) / 100;
        $rounded = ceil(($calculatedAmount > $amountMin['amount']) ? $calculatedAmount : $amountMin['amount']);

        $leftForPatherAndAdmin = $amountUser - $rounded;
        if($leftForPatherAndAdmin > 0){
            $amountToPartner = $percentToPartner / ($percentToAdmin + $percentToPartner) * $leftForPatherAndAdmin;
            $amountToAdmin = $percentToAdmin / ($percentToAdmin + $percentToPartner) * $leftForPatherAndAdmin;
        }

        return [$rounded, $amountToAdmin, $amountToPartner];
    }

    /**
     * Available to "reserve" (not really bet)
     *
     * @param LineCache $cachedLine
     * @param User $user
     * @return float
     */
    private function availableReserveForLine(LineCache $cachedLine, User $user): float
    {
        $userAlreadyBet = Redis::get('user_bet_on_line:'.$user->id.':'.$cachedLine->getUuid()) ?? 0;
        $partnerAlreadyBet = Redis::get('partner_bet_on_line:'.$user->partner->id.':'.$cachedLine->getUuid()) ?? 0;

        $userLimit = $user->partner->partnership->getMaxDepositUser();
        $partnerLimit = $user->partner->partnership->getMaxDepositPartner();

        $userLeft = $userLimit - $userAlreadyBet;
        $partnerLeft = $partnerLimit - $partnerAlreadyBet;

        return 1 > min($userLeft, $partnerLeft) ? 0 : min($userLeft, $partnerLeft);
    }

    /**
     * @param User $user
     * @param float $amount
     */
    private function saveAvailableReserveForLine(LineCache $cachedLine, User $user, float $amount): void
    {
        $user_key = 'user_bet_on_line:'.$user->id.':'.$cachedLine->getUuid();
        Redis::set($user_key, Redis::get($user_key) + $amount);

        $partner_key = 'partner_bet_on_line:'.$user->partner->id.':'.$cachedLine->getUuid();
        Redis::set($partner_key, Redis::get($partner_key) + $amount);
    }
}
