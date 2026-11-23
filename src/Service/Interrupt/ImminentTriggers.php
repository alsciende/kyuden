<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\Game;
use App\Message\ActionInterface;
use App\Trigger\ImminentTriggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The imminent triggering conditions of a message or an automatic transition, of every kind
 * (ImminentTriggerResolverInterface).
 */
final readonly class ImminentTriggers
{
    /**
     * @param iterable<ImminentTriggerResolverInterface> $resolvers
     */
    public function __construct(
        #[AutowireIterator(ImminentTriggerResolverInterface::class)]
        private iterable $resolvers,
    ) {
    }

    /**
     * @return list<ImminentTriggerInterface>
     */
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        $triggers = [];
        foreach ($this->resolvers as $resolver) {
            $triggers = [...$triggers, ...$resolver->resolveMessage($message, $game)];
        }

        return $triggers;
    }

    /**
     * @return list<ImminentTriggerInterface>
     */
    public function resolveTransition(string $transition, Game $game): array
    {
        $triggers = [];
        foreach ($this->resolvers as $resolver) {
            $triggers = [...$triggers, ...$resolver->resolveTransition($transition, $game)];
        }

        return $triggers;
    }
}
