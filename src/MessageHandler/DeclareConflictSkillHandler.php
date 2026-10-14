<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Rules\NoConflictException;
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
final readonly class DeclareConflictSkillHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $game->assertPlace('determine_result');
        $conflict = $this->gameStateManager->getState($game)->conflict ?? throw new NoConflictException();
        if (! in_array($player->getId(), [$conflict->attackingPlayerId, $conflict->defendingPlayerId], true)) {
            throw new NotInConflictException();
        }
    }

    public function __invoke(DeclareConflictSkill $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

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
