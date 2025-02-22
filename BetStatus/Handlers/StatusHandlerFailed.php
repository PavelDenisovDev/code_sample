<?php

declare(strict_types=1);

namespace Modules\Platform\Services\Bet\BetStatus\Handlers;

use Modules\DataHarvester\Entities\Bet;

class StatusHandlerFailed extends StatusHandlerRefund
{
    public const STATUSES = [Bet::STATUS_FAILED];
}