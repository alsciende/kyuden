<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\ConflictType;
use App\Enum\VictoryCondition;
use App\State\Model\ConflictState;
use App\State\Model\DuelState;
use App\State\Model\RingState;

/**
 * The state of a game as one of its players sees it (GameViewFactory). The rings, the conflict and the duel are public.
 */
final readonly class GameView
{
    /**
     * @param list<string>             $playerIds       player order
     * @param list<string>             $activePlayerIds
     * @param list<PlayerView>         $players         in player order of arrival
     * @param array<string, RingState> $rings           by element
     * @param list<string>             $availableActions the types of the messages the viewer can send now
     */
    public function __construct(
        public string $id,
        public ?string $place,
        public string $viewerId,
        public array $playerIds,
        public ?string $firstPlayerId,
        public array $activePlayerIds,
        public array $players,
        public array $rings,
        public ?ConflictState $conflict,
        public ?DuelState $duel,
        public ?string $imperialFavorHolderId,
        public ?ConflictType $imperialFavorSide,
        public ?string $gloryCountWinnerId,
        public ?string $winnerId,
        public ?VictoryCondition $victoryCondition,
        public array $availableActions,
    ) {
    }
}
