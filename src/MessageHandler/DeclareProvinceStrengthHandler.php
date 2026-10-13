<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\NotAttackedProvinceException;
use App\Exception\Rules\NotDefendingPlayerException;
use App\Message\DeclareProvinceStrength;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Replaces the printed strength of an attacked province (DetermineResultEnterListener) with the one declared by the
 * defending player.
 */
#[AsMessageHandler]
final readonly class DeclareProvinceStrengthHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
    ) {
    }

    public function __invoke(DeclareProvinceStrength $message): void
    {
        if ($message->getStrength() < 0) {
            throw new \InvalidArgumentException('Province strength cannot be negative.');
        }

        $game = $this->gameRepository->get($message->getGameId(), 'determine_result');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);

        $this->gameStateManager->withState($game, function (GameState $state) use ($player, $message): GameState {
            $conflict = $state->currentConflict();
            if ($player->getId() !== $conflict->defendingPlayerId) {
                throw new NotDefendingPlayerException();
            }
            if (! in_array($message->getProvinceId(), $conflict->attackedProvinceIds, true)) {
                throw new NotAttackedProvinceException();
            }

            $conflict->provinceStrengths[$message->getProvinceId()] = $message->getStrength();

            return $state;
        });
    }
}
