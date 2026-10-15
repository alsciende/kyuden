<?php

declare(strict_types=1);

namespace App\GameLog;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Event\GameEventInterface;

/**
 * Writes the log entry of a domain event carrying the GameLog attribute: its params are the properties of the event
 * named by the template. App\Kernel registers this listener for every such event of src/Event.
 */
final readonly class GameLogEventListener
{
    public function __construct(
        private GameLogger $gameLogger,
    ) {
    }

    public function __invoke(object $event): void
    {
        $reflection = new \ReflectionObject($event);
        $attribute = $reflection->getAttributes(GameLog::class)[0] ?? null;
        if ($attribute === null) {
            return;
        }

        if (! $event instanceof GameEventInterface) {
            throw new \LogicException(sprintf('The logged event %s must implement %s.', $event::class, GameEventInterface::class));
        }

        $template = $attribute->newInstance()->template;
        preg_match_all('/\{(\w+)\}/', $template, $matches);
        $params = [];
        foreach (array_unique($matches[1]) as $name) {
            if (! $reflection->hasProperty($name)) {
                throw new \LogicException(sprintf('The logged event %s has no property "%s".', $event::class, $name));
            }

            $property = $reflection->getProperty($name);
            $params[$name] = $this->revealed($event, $property) ?? $this->secret($event, $reflection, $property) ?? $property->getValue($event);
        }

        $this->gameLogger->log($event->getGame(), $template, $params);
    }

    /**
     * The param of a card the event reveals to every player (Revealed), null for any other property.
     */
    private function revealed(object $event, \ReflectionProperty $property): ?LogParam
    {
        if ($property->getAttributes(Revealed::class) === []) {
            return null;
        }

        $card = $property->getValue($event);
        if (! $card instanceof PhysicalCard) {
            throw new \LogicException(sprintf('The revealed property "%s" of %s must hold a card.', $property->getName(), $event::class));
        }

        return LogParam::card($card, null, null);
    }

    /**
     * The param of a property only some players know (VisibleTo), null for a public property.
     */
    private function secret(object $event, \ReflectionObject $reflection, \ReflectionProperty $property): ?LogParam
    {
        $attribute = $property->getAttributes(VisibleTo::class)[0] ?? null;
        if ($attribute === null) {
            return null;
        }

        $playerProperty = $attribute->newInstance()->player;
        $visibleTo = [];
        if ($playerProperty !== null) {
            $player = $reflection->getProperty($playerProperty)->getValue($event);
            if (! $player instanceof Player) {
                throw new \LogicException(sprintf('The property "%s" of %s must hold a player.', $playerProperty, $event::class));
            }

            $visibleTo = [$player->getId()];
        }

        $value = $property->getValue($event);
        if ($value instanceof PhysicalCard) {
            return LogParam::card($value, $visibleTo, $visibleTo);
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if (! is_int($value) && ! is_string($value) && ! is_bool($value) && $value !== null) {
            throw new \LogicException(sprintf('The secret property "%s" of %s must hold a card, a scalar value or a backed enum.', $property->getName(), $event::class));
        }

        return LogParam::value($value, $visibleTo);
    }
}
