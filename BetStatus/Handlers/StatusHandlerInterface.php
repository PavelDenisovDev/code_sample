<?php

declare(strict_types=1);

namespace Modules\Platform\Services\Bet\BetStatus\Handlers;

use Modules\DataHarvester\Entities\Bet;

interface StatusHandlerInterface
{
    public const STATUSES = [];

    /**
     * @param Bet $betRequest
     */
    public function process(Bet $betRequest): void;

    /**
     * @param Bet $betRequest
     * @return bool
     */
    public function isProcessable(Bet $betRequest): bool;
}