<?php

declare(strict_types=1);

namespace Modules\Platform\Services\Bet\BetStatus;

use Illuminate\Support\Collection;
use Modules\DataHarvester\Entities\Bet;
use Modules\Platform\Entities\Bet\BetRequest;

/**
 * Manages the status transition from Bet to BetRequest
 *
 * Class StatusTransitionManager
 * @package Modules\Platform\Services\Bet\BetStatus
 */
class StatusTransitionHandler
{
    /**
     * @param BetRequest $betRequest
     * @return Collection|StatusChangeAction[]
     */
    public function applyAndGetStatusChanges(BetRequest $betRequest): Collection
    {
        $changes = new Collection();
        foreach ($this->getStatusActions() as $action){
            $change = $this->applyAction($betRequest, $action);
            if($change instanceof StatusChangeAction){
                $changes->push($change);
            }
        }
        return $changes;
    }

    /**
     * @param BetRequest $betRequest
     * @param array $action
     * @return StatusChangeAction|null
     */
    private function applyAction(BetRequest $betRequest, array $action): ?StatusChangeAction
    {
        $statuses = [];
        foreach ($betRequest->bets()->get() as $bet){
            $statuses[] = $bet->status;
        }

        $primary_status_present = false;
        foreach ($action['primaryStatuses'] as $primaryStatus){
            if(in_array($primaryStatus, $statuses)){
                $primary_status_present = true;
                break;
            }
        }

        if(!$primary_status_present){
            return null;
        }

        if(isset($action['secondaryStatuses'])){
            $secondary_status_present = false;
            foreach ($action['secondaryStatuses'] as $secondaryStatus){
                if(in_array($secondaryStatus, $statuses)){
                    $secondary_status_present = true;
                    break;
                }
            }
            if(!$secondary_status_present){
                return null;
            }
        }

        $restricted_status_present = false;
        if(isset($action['restrictedStatuses'])){
            if($action['restrictedStatuses'] === '*'){
                foreach ($statuses as $status){
                    if(!in_array($status, $action['primaryStatuses'])){
                        $restricted_status_present = true;
                    }
                }
            }else{
                foreach ($statuses as $status){
                    if(in_array($status, $action['restrictedStatuses'])){
                        $restricted_status_present = true;
                    }
                }
            }
        }

        if($restricted_status_present) {
            return null;
        }

        return $action['action']($betRequest);
    }

    /**
     * primaryStatuses -> at least one primary status must be present
     * secondaryStatuses -> at least one secondary status must be present
     * restrictedStatuses -> none of the restricted statuses must be present
     *
     * @return array
     */
    private function getStatusActions(): array
    {
        return  [
            [
                'primaryStatuses' => [
                    Bet::STATUS_FAILED,
                    Bet::STATUS_REFUNDED,
                    Bet::STATUS_CANCELED,
                    Bet::STATUS_NOT_ACCEPTED
                ],
                'restrictedStatuses' => '*',
                'action' => function(BetRequest $betRequest){
                    return new StatusChangeAction($betRequest,Bet::STATUS_CANCELED);
                }
            ],
            [
                'primaryStatuses' => [Bet::STATUS_PENDING],
                'action' => function(BetRequest $betRequest){}
            ],
            [
                'primaryStatuses' => [Bet::STATUS_PENDING_ACCEPTANCE],
                'action' => function(BetRequest $betRequest){}
            ],
            [
                'primaryStatuses' => [
                    Bet::STATUS_FAILED,
                    Bet::STATUS_REFUNDED,
                    Bet::STATUS_CANCELED
                ],
                'secondaryStatuses' => [
                    Bet::STATUS_ACCEPTED,
                    Bet::STATUS_LOSS,
                    Bet::STATUS_WIN,
                ],
                'action' => function(BetRequest $betRequest){
                    foreach ($betRequest->bets as $bet){
                        /** @var Bet $bet */
                        if (
                            in_array(
                                $bet->status,
                                [
                                    Bet::STATUS_FAILED,
                                    Bet::STATUS_REFUNDED,
                                    Bet::STATUS_CANCELED
                                ],
                                true
                            )
                        ) {
                            $bookMakerData = $betRequest->getBookmakerData($bet->getBookmaker());
                            $betRequest->setAmountUser($betRequest->getAmountUser() - $bookMakerData['amount_user']);
                        }
                    }
                    $betRequest->save();
                }
            ],
            [
                'primaryStatuses' => [Bet::STATUS_ACCEPTED],
                'restrictedStatuses' => '*',
                'action' => function(BetRequest $betRequest){
                    return new StatusChangeAction($betRequest,Bet::STATUS_ACCEPTED);
                }
            ],
            [
                'primaryStatuses' => [Bet::STATUS_WIN],
                'restrictedStatuses' => '*',
                'action' => function(BetRequest $betRequest){
                    return new StatusChangeAction($betRequest,Bet::STATUS_WIN);
                }
            ],
            [
                'primaryStatuses' => [Bet::STATUS_LOSS],
                'restrictedStatuses' => '*',
                'action' => function(BetRequest $betRequest){
                    return new StatusChangeAction($betRequest,Bet::STATUS_LOSS);
                }
            ],
        ];
    }
}
