<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\NotInConflictException;
use App\Message\DeclareConflictSkill;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Both players of the conflict declare their total skill, in any order; a player can declare it again until the other
 * one has declared theirs. The result is determined once both have declared (`result_determined`).
 */
#[AsMessageHandler]
final readonly class DeclareConflictSkillHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
    ) {
    }

    public function __invoke(DeclareConflictSkill $message): void
    {
        $game = $this->gameRepository->get($message->getGameId(), 'determine_result');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);

        $this->gameStateManager->withState($game, function (GameState $state) use ($player, $message): GameState {
            $conflict = $state->currentConflict();
            match ($player->getId()) {
                $conflict->attackingPlayerId => $conflict->attackerSkill = $message->getSkill(),
                $conflict->defendingPlayerId => $conflict->defenderSkill = $message->getSkill(),
                default => throw new NotInConflictException(),
            };

            return $state;
        });
    }
}
