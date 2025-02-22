<?php

namespace Modules\Platform\Services\Bet;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\DataHarvester\DTO\BookMaker\BookmakerInterface;
use Modules\DataHarvester\Entities\Bet;
use Modules\DataHarvester\Entities\LineCache;
use Modules\Platform\Entities\Bet\BetRequest;
use Modules\Platform\Exceptions\LowBookmakerBalanceException;
use Modules\Platform\Exceptions\ValidationException;
use Modules\Platform\Services\Bet\BetStatus\StatusChanger;
use Modules\Platform\Services\BetPlacement\Exceptions\BetPlacementApiException;
use Modules\Platform\Services\ErrorReporter;
use Ramsey\Uuid\Uuid;

class BetManager
{
    /**
     * @var BetRequestFactory
     */
    private $betRequestFactory;

    /**
     * @var BetFactory
     */
    private $betFactory;

    /**
     * @var StatusChanger
     */
    private $betStatusChanger;

    public function __construct(
        BetRequestFactory $betRequestFactory,
        BetFactory $betFactory,
        StatusChanger $betStatusChanger
    )
    {
        $this->betRequestFactory = $betRequestFactory;
        $this->betFactory = $betFactory;
        $this->betStatusChanger = $betStatusChanger;
    }

    /**
     * @param Request $request
     * @return array|BetRequest[]
     * @throws ValidationException
     */
    public function createBetRequest(Request $request): array
    {
        $user = $this->getUserFromRequest($request);

        if(!$user){
            throw new ValidationException(__('User not found'));
        }

        return $this->createBetRequestForUser($request, $user);
    }

    /**
     * @param Request $request
     * @param User $user
     * @return array
     * @throws ValidationException
     * @throws \Modules\Platform\Exceptions\BelowMinAmountException
     */
    public function createBetRequestForUser(Request $request, User $user): array
    {
        $bets = $request->request->get('bet', []);

        $cachedLine = LineCache::where('uuid', $request->request->get('uuid'))->first();

        $requests = [];

        $parent_uuid = Uuid::uuid4();

        try {
            DB::beginTransaction();
            foreach ($bets as $bet) {
                if ($ttl = $request->request->get('ttl')) {
                    $bet['ttl'] = $ttl;
                }
                $betRequest = $this->betRequestFactory->createBetRequest($cachedLine, $user, $bet);
                $betRequest->setLineType($request->request->get('line_type'));
                $this->betStatusChanger->changeStatus($betRequest, Bet::STATUS_PENDING);

                $requests[] = $betRequest;

                Log::info('Creating bet request for line ' . $cachedLine->uuid);
                $this->processBetRequest($betRequest, $parent_uuid);
            }
            DB::commit();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if($e instanceof LowBookmakerBalanceException){
                throw new ValidationException($e->getMessage());
            }
            DB::rollback();
            ErrorReporter::reportError($e);
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());

            if ($e instanceof BetPlacementApiException) {
                throw new ValidationException(__($e->getMessage()));
            }

            throw new ValidationException(__('An error occurred, please try again.'));
        }

        return $requests;
    }

    /**
     * @param BetRequest $betRequest
     * @param string $parent_uuid
     * @throws \Modules\Platform\Exceptions\BelowMinAmountException
     */
    public function processBetRequest(BetRequest $betRequest, string $parent_uuid): void
    {
        $bets = $this->betFactory->createBets($betRequest, $parent_uuid);
        foreach ($bets as $bet){
            $bet->save();
            $betRequest->addBookmakerData($bet->getBookmaker(), [
                'bet_id' => $bet->id,
                'system_amount' => $bet->amount,
                'user_amount' => round($bet->amount / $betRequest->getAmountSystem() * $betRequest->getAmountUser(),2)
            ]);
            $betRequest->save();

            if($bet->type === Bet::TYPE_API) {
                $bet->getRequest()->refresh();
                $this->sendBet($bet);
            }
        }
    }

    /**
     * @param Bet $bet
     */
    public function sendBet(Bet $bet): void
    {
        /** @var BookmakerInterface $bookmaker */
        $bookmaker = $bet->getBookmaker();
        $betInfo = $bookmaker->getDataFetcher()->bet($bet);
        if (is_array($betInfo)) {
            $bet->response_metadata = json_encode($betInfo);
        } else {
            $bet->response_metadata = $betInfo;
        }

        $bookmaker->getStatusProcessor()->processStatus($bet);
    }

    /**
     * @param Request $request
     * @return User
     */
    public function getUserFromRequest(Request $request): ?User
    {
        if(!Auth::user()){
            return null;
        }
        if(Auth::user()->hasRole('Partner')){
            $id = $request->request->get('user');
            /** @var User $user */
            $user = User::find($id);
            if(!$user || !$user->partner || $user->partner->id !== Auth::user()->id){
                return null;
            }
            return $user;
        }
        return Auth::user();
    }
}
