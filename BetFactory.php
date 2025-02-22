<?php

namespace Modules\Platform\Services\Bet;


use Illuminate\Container\RewindableGenerator;
use Modules\DataHarvester\DTO\BookMaker\BookmakerInterface;
use Modules\DataHarvester\Entities\Bet;
use Modules\Platform\Entities\Bet\BetRequest;
use Modules\Platform\Exceptions\BelowMinAmountException;
use Modules\Platform\Services\TransactionProcessor;

class BetFactory
{

    /**
     * @var BookmakerInterface[]|array
     */
    private $bookmakers;

    public function __construct(
        RewindableGenerator $bookmakers
    ) {
        $this->bookmakers = $bookmakers;
    }

    /**
     * @param BetRequest $request
     * @param string $parant_uuid
     * @return array|Bet[]
     * @throws BelowMinAmountException
     */
    public function createBets(BetRequest $request, string  $parant_uuid): array
    {
        $user = $request->getUser();
        $fee = $user->getBettingFee();


        $bookmakerBets = $this->determineBookmakers($request);

        $bets = [];
        foreach ($bookmakerBets as $bookmakerBet){
            $bet = new Bet();
            $bet->company = $bookmakerBet['bookmaker']->getName();
            $bet->type = $this->getBetType($request);
            $bet->amount = $bookmakerBet['amount'];
            $bet->status = Bet::STATUS_PENDING;
            $bet->request_id = $request->id;
            $bet->parent_uuid = $parant_uuid;
            $bet->odds = $request->getOddsUser() / (1 - ($fee / 100));
            $bets[] = $bet;
        }

        return $bets;
    }

    /**
     * [[
     *  bookmaker => BookmakerInterface
     *  amount => float
     * ]]
     *
     * @param BetRequest $request
     * @return array
     * @throws BelowMinAmountException
     */
    private function determineBookmakers(BetRequest $request): array
    {
        $bookmakerData = [];
        foreach ($this->bookmakers as $bookmaker){
            $requestData = $request->getBookmakerData($bookmaker);
            if($requestData){
                $bookmakerData[] = array_merge($request->getBookmakerData($bookmaker),['bookmaker' => $bookmaker]);
            }

        }

        // sort by highest price
        usort($bookmakerData, function ($v, $v2){
            return $v['price'] < $v2['price'];
        });


        // deal with min amount
        $minAmountData = [];
        foreach ($bookmakerData as $data){
            if($data['minAmount'] > $request->getAmountSystem()){
                continue;
            }else{
                $minAmountData[] = $data;
            }
        }
        if(count($minAmountData ) < 1){
            throw new BelowMinAmountException(__('Below minimum bet amount'));
        }

        // do we split the bet between bookmakers?
        $split = false;
        $bookmakersAmounts = [];
        $remainingAmount = $request->getAmountSystem();
        foreach ($minAmountData as $data){
            if(!$split && $data['maxAmount'] > $remainingAmount){
                return [[
                    'bookmaker' => $data['bookmaker'],
                    'amount' => $remainingAmount
                ]];
            }else{
                if($remainingAmount){
                    $split = true;
                    $amount = (($data['maxAmount'] < $remainingAmount) ? $data['maxAmount'] : $remainingAmount);
                    $bookmakersAmounts[] = [
                        'bookmaker' => $data['bookmaker'],
                        'amount' => $amount
                    ];
                    $remainingAmount -= $amount;
                }
            }
        }
        if($remainingAmount){
            $request->setAmountSystem($request->getAmountSystem() - $remainingAmount);
        }

        return $bookmakersAmounts;
    }

    /**
     * @param BetRequest $request
     * @return int
     */
    private function getBetType(BetRequest $request): int
    {
        if($request->getOddsBase() >= $request->getOddsUser()){
            return Bet::TYPE_API;
        }else{
            return Bet::TYPE_AUTOMATIC;
        }
    }


}
