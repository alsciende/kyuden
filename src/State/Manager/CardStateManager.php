<?php

declare(strict_types=1);

namespace App\State\Manager;

use App\Entity\PhysicalCard;
use App\State\Model\CardState;

/**
 * @extends AbstractStateManager<PhysicalCard, CardState>
 */
readonly class CardStateManager extends AbstractStateManager
{
    protected function getStateClass(): string
    {
        return CardState::class;
    }
}
