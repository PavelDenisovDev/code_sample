<?php

declare(strict_types=1);

namespace Modules\Platform\Services\Bet\BetStatus;

use Illuminate\Support\Facades\Log;
use Modules\DataHarvester\Entities\Bet;
use Modules\Platform\Entities\Bet\BetRequest;
use Modules\Platform\Services\BetRequest\BetStatus\BetRequestStatusProcessor;

/**
 * Changes the status of a Bet of BetRequest,
 * performing a chain of side-effects of that change
 *
 * Class StatusChanger
 * @package Modules\Platform\Services\Bet\BetStatus
 */
class StatusChanger
{
    /**
     * @var BetRequestStatusProcessor
     */
    private $betRequestStatusProcessor;

    /**
     * @var BetRequestStatusProcessor
     */
    private $betStatusProcessor;

    /**
     * @var StatusTransitionHandler
     */
    private $statusTransitionHandler;

    public function __construct(
        BetRequestStatusProcessor $betRequestStatusProcessor,
        BetStatusProcessor $betStatusProcessor,
        StatusTransitionHandler $statusTransitionHandler
    ) {
        $this->betRequestStatusProcessor = $betRequestStatusProcessor;
        $this->statusTransitionHandler = $statusTransitionHandler;
        $this->betStatusProcessor = $betStatusProcessor;
    }

    /**
     * Wait for php 8 to change param to BetRequest|Bet :)
     *
     * @param $bet
     * @param int $status
     */
    public function changeStatus($bet, int $status): void
    {
        if ($bet instanceof BetRequest) {
            Log::debug('betRequest_id: '.$bet->id.' - changeStatus - instanceof BetRequest - start - status - '.$status);
            $this->changeBetRequestStatus($bet, $status);
            Log::debug('betRequest_id: '.$bet->id.' - changeStatus - instanceof BetRequest - end - status - '.$status);
        } elseif ($bet instanceof Bet) {
            $this->changeBetStatus($bet, $status);
        }
    }

    /**
     * @param Bet $bet
     * @param int $status
     */
    private function changeBetStatus(Bet $bet, int $status): void
    {
        $bet->status = $status;
        $bet->save();
        $changes = $this->statusTransitionHandler->applyAndGetStatusChanges($bet->getRequest());

        $this->betStatusProcessor->process($bet);

        foreach ($changes as $change){
            $this->changeStatus($change->getBetRequest(), $change->getStatus());
        }
    }

    /**
     * @param BetRequest $betRequest
     * @param int $status
     */
    private function changeBetRequestStatus(BetRequest $betRequest, int $status): void
    {
        $betRequest->status = $status;
        $betRequest->save();
        $this->betRequestStatusProcessor->process($betRequest);
    }
}
