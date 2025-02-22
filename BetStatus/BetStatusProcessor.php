<?php

namespace Modules\Platform\Services\Bet\BetStatus;

use Illuminate\Support\Collection;
use Modules\DataHarvester\Entities\Bet;
use Modules\Platform\Services\Bet\BetStatus\Handlers\StatusHandlerCollection;
use Modules\Platform\Services\Bet\BetStatus\Handlers\StatusHandlerInterface;

class BetStatusProcessor
{
    /**
     * @var Collection|StatusHandlerInterface[]
     */
    private $statusHandlers;

    /**
     * BetRequestStatusProcessor constructor.
     * @param StatusHandlerCollection|StatusHandlerInterface[] $statusHandlers
     */
    public function __construct(StatusHandlerCollection $statusHandlers)
    {
        $this->statusHandlers = $statusHandlers;
    }

    /**
     * @param Bet $bet
     */
    public function process(Bet $bet): void
    {
        foreach ($this->statusHandlers as $handler)
        {
            if ($handler->isProcessable($bet)) {
                $handler->process($bet);
            }
        }
    }
}