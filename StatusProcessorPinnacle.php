<?php

namespace Modules\Platform\Services\Bet;

use Modules\DataHarvester\Entities\Bet;
use Modules\Platform\Services\Bet\BetStatus\StatusChanger;
use Modules\Platform\Services\BetPlacement\Exceptions\BetPlacementApiException;

class StatusProcessorPinnacle implements StatusProcessorInterface
{
    /**
     * @var StatusChanger
     */
    private $statusChanger;

    public function __construct(StatusChanger $statusChanger)
    {
        $this->statusChanger = $statusChanger;
    }

    /**
     * @param Bet $bet
     */
    public function processStatus(Bet $bet): void
    {
        $metadata = $bet->getResponseMetaData();
        if(isset($metadata->status)){
            switch ($metadata->status){
                case 'ACCEPTED':
                    $this->statusChanger->changeStatus($bet, Bet::STATUS_ACCEPTED);
                    break;
                case 'PENDING_ACCEPTANCE':
                    $this->statusChanger->changeStatus($bet, Bet::STATUS_PENDING_ACCEPTANCE);
                    break;
                default:
                    $this->statusChanger->changeStatus($bet, Bet::STATUS_FAILED);
                    throw new BetPlacementApiException('Bet not accepted. Please, try again');
            }
        }else{
            $this->statusChanger->changeStatus($bet, Bet::STATUS_FAILED);
            throw new BetPlacementApiException('No message from bookmaker');
        }
    }
}