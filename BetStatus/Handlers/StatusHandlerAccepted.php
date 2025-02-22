<?php

namespace Modules\Platform\Services\Bet\BetStatus\Handlers;

use Modules\DataHarvester\Entities\Bet;
use Modules\Platform\Entities\Bet\BetRequest;

class StatusHandlerAccepted extends AbstractStatusHandler
{
    public const STATUSES = [Bet::STATUS_ACCEPTED];

    public function process(Bet $bet): void
    {
        $request = $bet->getRequest();
        $price = isset($bet->getResponseMetaData()->{$request->getSource().'Bet'}) ?
            $bet->getResponseMetaData()->{$request->getSource().'Bet'}->price:
            $bet->getResponseMetaData()->price;
        $bet->odds = $price;
        $bet->save();
    }
}