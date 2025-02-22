<?php

declare(strict_types=1);

namespace Modules\Platform\Services\Bet\BetStatus;

use Modules\Platform\Entities\Bet\BetRequest;

class StatusChangeAction
{
    /**
     * @var int
     */
    private $status;

    /**
     * @var BetRequest
     */
    private $betRequest;

    public function __construct(BetRequest $betRequest, int $status)
    {
        $this->status = $status;
        $this->betRequest = $betRequest;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return BetRequest
     */
    public function getBetRequest(): BetRequest
    {
        return $this->betRequest;
    }
}