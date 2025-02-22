<?php

namespace Modules\Platform\Services\Bet;

use Modules\DataHarvester\Entities\Bet;

interface StatusProcessorInterface
{
    public function processStatus(Bet $bet);
}