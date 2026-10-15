<?php

declare(strict_types=1);

namespace App\GameLog;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\Element;
use App\Enum\StackType;

use function Symfony\Component\String\u;

/**
 * A param of a log entry, encoded as stored in GameLogEntry::$params. Its `type` tells the client how to show it:
 * - `player`: `id`;
 * - `card`: `id` and `code`; `visible_to` lists who knows the card (its id), `code_visible_to` who knows its code;
 * - `stack`: `stack_type` (snake case name of StackType), `player_id` (owner) and `id` (null for the new stack of a
 *   card entering play);
 * - `ring`: `element`;
 * - `value`: `value` (scalar or null); `visible_to` lists who knows it;
 * - `list`: `items`, a list of params.
 *
 * A visibility is null when the param is public, else the list of the ids of the players who know it (empty: nobody).
 */
final readonly class LogParam
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public array $data,
    ) {
    }

    public static function player(Player $player): self
    {
        return new self([
            'type' => 'player',
            'id' => $player->getId(),
        ]);
    }

    /**
     * @param list<string>|null $visibleTo     who knows the card (its id)
     * @param list<string>|null $codeVisibleTo who knows its code
     */
    public static function card(PhysicalCard $card, ?array $visibleTo, ?array $codeVisibleTo): self
    {
        return new self([
            'type' => 'card',
            'id' => $card->getId(),
            'code' => $card->getLogicalCard()->getId(),
            'visible_to' => $visibleTo,
            'code_visible_to' => $codeVisibleTo,
        ]);
    }

    public static function stack(Stack $stack): self
    {
        return self::stackOf($stack->getType(), $stack->getPlayer(), $stack);
    }

    /**
     * A stack that may not exist yet: the new stack of a card entering play.
     */
    public static function stackOf(StackType $type, Player $owner, ?Stack $stack): self
    {
        return new self([
            'type' => 'stack',
            'id' => $stack?->getId(),
            'stack_type' => u($type->name)->snake()->toString(),
            'player_id' => $owner->getId(),
        ]);
    }

    public static function ring(Element $element): self
    {
        return new self([
            'type' => 'ring',
            'element' => $element->value,
        ]);
    }

    /**
     * @param list<string>|null $visibleTo
     */
    public static function value(int|string|bool|null $value, ?array $visibleTo = null): self
    {
        return new self([
            'type' => 'value',
            'value' => $value,
            'visible_to' => $visibleTo,
        ]);
    }

    /**
     * @param list<self> $items
     */
    public static function list(array $items): self
    {
        return new self([
            'type' => 'list',
            'items' => array_map(fn (self $item): array => $item->data, $items),
        ]);
    }
}
