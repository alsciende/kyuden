<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\NotActivePlayerException;
use App\Message\ClaimImperialFavor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The winner of the glory count, the only active player (ClaimImperialFavorEnterListener), takes the Imperial Favor,
 * even if they already have it, and chooses its side. The conflict phase then ends.
 */
#[AsMessageHandler]
final readonly class ClaimImperialFavorHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private WorkflowInterface $gameStateMachine,
    ) {
    }

    public function __invoke(ClaimImperialFavor $message): void
    {
        $game = $this->gameRepository->get($message->getGameId(), 'claim_imperial_favor');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }

        $this->gameStateManager->withState($game, function (GameState $state) use ($player, $message): GameState {
            $state->imperialFavorHolderId = $player->getId();
            $state->imperialFavorSide = $message->getSide();
            $state->activePlayerIds = [];

            return $state;
        });

        $this->gameStateMachine->apply($game, 'imperial_favor_claimed');
    }
}
