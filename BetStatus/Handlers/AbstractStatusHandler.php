<?php

declare(strict_types=1);

namespace Modules\Platform\Services\Bet\BetStatus\Handlers;

use Modules\DataHarvester\Entities\Bet;

abstract class AbstractStatusHandler implements StatusHandlerInterface
{
    /**
     * {@inheritDoc}
     */
    public function isProcessable(Bet $bet): bool
    {
        if (in_array($bet->status, static::STATUSES, true)) {
            return true;
        }
        return false;
    }
}