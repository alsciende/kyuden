<?php

declare(strict_types=1);

namespace App\GameLog;

use App\Entity\Game;
use App\Entity\GameLogEntry;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\Element;
use App\View\Visibility;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes the log of a game: one entry per change of its state, with a template and its params. The entry belongs to
 * the version of the game the message being handled leads to (EventRecordingMiddleware increments it afterwards).
 *
 * Params are given as LogParam, or as values converted here: Player, PhysicalCard (known to the players who see it
 * where it is now), Stack, Element (a ring), backed enums and scalars (public values), lists of those.
 */
final readonly class GameLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Visibility $visibility,
    ) {
    }

    /**
     * @param array<string, mixed> $params by placeholder name; the params missing from the template are not stored
     */
    public function log(Game $game, string $template, array $params = []): void
    {
        preg_match_all('/\{(\w+)\}/', $template, $matches);
        $encoded = [];
        foreach (array_unique($matches[1]) as $name) {
            if (! array_key_exists($name, $params)) {
                throw new \LogicException(sprintf('The log template "%s" has no param "%s".', $template, $name));
            }

            $encoded[$name] = $this->toParam($game, $params[$name])->data;
        }

        $this->entityManager->persist(new GameLogEntry($game, $game->getVersion() + 1, $template, $encoded));
    }

    /**
     * The players of the game for whom the condition holds: null when it holds for all of them.
     *
     * @param \Closure(Player): bool $condition
     *
     * @return list<string>|null
     */
    public function playersWhere(Game $game, \Closure $condition): ?array
    {
        $playerIds = [];
        $all = true;
        foreach ($game->getPlayers() as $player) {
            if ($condition($player)) {
                $playerIds[] = $player->getId();
            } else {
                $all = false;
            }
        }

        return $all ? null : $playerIds;
    }

    private function toParam(Game $game, mixed $value): LogParam
    {
        return match (true) {
            $value instanceof LogParam => $value,
            $value instanceof Player => LogParam::player($value),
            $value instanceof PhysicalCard => LogParam::card(
                $value,
                $this->playersWhere($game, fn (Player $viewer): bool => $this->visibility->isStackVisibleTo($value->getStack()->getType(), $value->getStack()->getPlayer(), $viewer)),
                $this->playersWhere($game, fn (Player $viewer): bool => $this->visibility->isCardInStackVisibleTo($value, $value->getStack(), $viewer)),
            ),
            $value instanceof Stack => LogParam::stack($value),
            $value instanceof Element => LogParam::ring($value),
            $value instanceof \BackedEnum => LogParam::value($value->value),
            is_int($value), is_string($value), is_bool($value), $value === null => LogParam::value($value),
            is_array($value) && array_is_list($value) => LogParam::list(array_map(fn (mixed $item): LogParam => $this->toParam($game, $item), $value)),
            default => throw new \LogicException(sprintf('A value of type %s cannot be a log param.', get_debug_type($value))),
        };
    }
}
