<?php

declare(strict_types=1);

namespace App\GameLog;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\View\Visibility;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\LeaveEvent;

/**
 * Writes the log entry of every card transition, from the `log` metadata of the transition (required). Its params:
 * - `card`: the card, known to the players who see it before or after the transition (App\View\Visibility);
 * - `owner`: the player owning the card;
 * - `from`, `to`: the stacks the card leaves and goes to (the same for a transition that does not move it);
 * - `controller`: the player given in the `controller` key of the transition context, if any (take_control);
 * - `player`: the player given in the `player` key of the transition context, if any (the player playing the card);
 * - `host`: the card it goes under, when it goes to an Underneath stack (place_under);
 * - `character`: when the template names it, the character the card is attached to as an attachment
 *   (play_as_attachment), or detached from (detach).
 *
 * Written when the card leaves its place, before the consequences of the transition (LeavePlayListener), which write
 * their own entries: the position the card goes to is read from the metadata of the transition, as
 * App\Workflow\CardTransitionListener applies it.
 */
#[AsEventListener(event: 'workflow.leave', priority: 100)]
final readonly class CardTransitionLogListener
{
    public function __construct(
        private GameLogger $gameLogger,
        private Visibility $visibility,
    ) {
    }

    /**
     * @param LeaveEvent<object> $event
     */
    public function __invoke(LeaveEvent $event): void
    {
        $card = $event->getSubject();
        $transition = $event->getTransition();
        if (! $card instanceof PhysicalCard || $transition === null) {
            return;
        }

        $template = $event->getMetadata('log', $transition);
        if (! is_string($template)) {
            throw new \LogicException(sprintf("Card transition %s must give its 'log' metadata.", $transition->getName()));
        }

        $from = $card->getStack();
        $toType = $event->getMetadata('stack', $transition);
        $toType = $toType instanceof StackType ? $toType : $from->getType();
        $orientation = $event->getMetadata('orientation', $transition);
        $orientation = $orientation instanceof Orientation ? $orientation : $card->getOrientation();
        $to = $event->getMetadata('character', $transition) === true
            ? null // a new stack (detach)
            : $this->getTargetStack($card, $from, $toType, $event->getContext());
        $toOwner = $to?->getPlayer() ?? $card->getPlayer();

        $game = $card->getGame();
        // the card it goes under (place_under)
        $host = $to?->getHost();
        // the character it is attached to, or detached from
        $character = str_contains($template, '{character}')
            ? ($to?->getOrderedCards()[0] ?? null) ?? $card->getAttachedCharacter()
            : null;
        $controller = $event->getContext()['controller'] ?? null;
        // the player playing the card, whoever owns it
        $player = $event->getContext()['player'] ?? null;
        $this->gameLogger->log($game, $template, [
            'card' => LogParam::card(
                $card,
                $this->gameLogger->playersWhere($game, fn (Player $viewer): bool => $this->visibility->isStackVisibleTo($from->getType(), $from->getPlayer(), $viewer)
                    || $this->visibility->isStackVisibleTo($toType, $toOwner, $viewer)),
                $this->gameLogger->playersWhere($game, fn (Player $viewer): bool => $this->visibility->isCardInStackVisibleTo($card, $from, $viewer)
                    || $this->visibility->isCardVisibleTo($card, $toType, $toOwner, $orientation, $viewer)),
            ),
            'owner' => $card->getPlayer(),
            'from' => $from,
            'to' => LogParam::stackOf($toType, $toOwner, $to),
            ...($host instanceof PhysicalCard ? ['host' => $this->cardParam($host, $host->getStack())] : []),
            ...($character instanceof PhysicalCard ? ['character' => $this->cardParam($character, $character->getStack())] : []),
            ...($controller instanceof Player ? ['controller' => $controller] : []),
            ...($player instanceof Player ? ['player' => $player] : []),
        ]);
    }

    /**
     * A card that stays where it is, as the players see it there.
     */
    private function cardParam(PhysicalCard $card, Stack $stack): LogParam
    {
        $game = $card->getGame();

        return LogParam::card(
            $card,
            $this->gameLogger->playersWhere($game, fn (Player $viewer): bool => $this->visibility->isStackVisibleTo($stack->getType(), $stack->getPlayer(), $viewer)),
            $this->gameLogger->playersWhere($game, fn (Player $viewer): bool => $this->visibility->isCardInStackVisibleTo($card, $stack, $viewer)),
        );
    }

    /**
     * The stack the card goes to, as CardTransitionListener resolves it; null for the new stack of a card entering
     * play.
     *
     * @param array<mixed> $context
     */
    private function getTargetStack(PhysicalCard $card, Stack $from, StackType $type, array $context): ?Stack
    {
        $stack = $context['stack'] ?? null;

        return match (true) {
            $type === $from->getType() && $stack === null => $from,
            $stack instanceof Stack => $stack,
            $type->isUnique() => $card->getPlayer()->getUniqueStack($type),
            default => null,
        };
    }
}
