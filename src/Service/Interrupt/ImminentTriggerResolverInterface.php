<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\Game;
use App\Message\ActionInterface;
use App\Trigger\ImminentTriggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What a message or an automatic transition of the game would make occur, for one kind of triggering condition: read
 * before it is handled (InterruptWindowMiddleware, WorkflowTransitionMiddleware), from the state only.
 */
#[AutoconfigureTag]
interface ImminentTriggerResolverInterface
{
    /**
     * The conditions the message would make occur, none when it would be refused (checked here, so that no window
     * opens for what the engine would not do).
     *
     * @return list<ImminentTriggerInterface>
     */
    public function resolveMessage(ActionInterface $message, Game $game): array;

    /**
     * The conditions the automatic transition would make occur (metadata `interrupt`).
     *
     * @return list<ImminentTriggerInterface>
     */
    public function resolveTransition(string $transition, Game $game): array;
}
