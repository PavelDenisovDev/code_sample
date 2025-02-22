<?php

namespace Modules\Platform\Services\Bet;

use Modules\DataHarvester\Entities\Bet;

class StatusProcessorISN implements StatusProcessorInterface
{
    public function processStatus(Bet $bet)
    {
        $metadata = $bet->getResponseMetaData();

        if(isset($metadata->success) && $metadata->success === true){
            $bet->status = Bet::STATUS_ACCEPTED;
        }else{
            $bet->status = Bet::STATUS_FAILED;
        }

        $bet->save();
    }
}