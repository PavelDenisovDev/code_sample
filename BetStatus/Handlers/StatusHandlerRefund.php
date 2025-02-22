<?php

declare(strict_types=1);

namespace Modules\Platform\Services\Bet\BetStatus\Handlers;

use Modules\DataHarvester\Entities\Bet;
use Modules\Platform\Entities\Bet\BetRequest;
use Modules\Platform\Services\TransactionProcessor;

/**
 * Handles a list of actions if bet is refunded
 *
 * Class StatusHandlerLoss
 *
 * @package Modules\Platform\Services\Bet\BetStatus
 */
class StatusHandlerRefund extends AbstractStatusHandler
{
    public const STATUSES = [Bet::STATUS_REFUNDED,Bet::STATUS_NOT_ACCEPTED];

    /**
     * @var TransactionProcessor
     */
    private $transactionProcessor;

    public function __construct(TransactionProcessor $processor)
    {
        $this->transactionProcessor = $processor;
    }

    /**
     * {@inheritDoc}
     */
    public function isProcessable(Bet $bet): bool
    {
        $processable = parent::isProcessable($bet);
        return $processable && $bet->amount;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Bet $bet): void
    {
        $this->transactionProcessor->refundBet($bet);
    }
}
