<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\DuelStepException;
use App\Exception\Rules\NoHonorBidException;
use App\Exception\Rules\NotInDuelException;
use App\Message\DeclareDuelStatistic;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\HonorBidService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Once the honor dials of the duel are revealed, both players involved declare their dueling statistic, in any order;
 * a player can declare it again until the other one has declared theirs. Once both are declared, the honor is
 * transferred (D.5, with the bids changed by cards since the reveal) and the result is determined (D.7).
 */
#[AsMessageHandler]
final readonly class DeclareDuelStatisticHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private HonorBidService $honorBidService,
    ) {
    }

    public function __invoke(DeclareDuelStatistic $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);

        $this->gameStateManager->withState($game, function (GameState $state) use ($player, $message): GameState {
            $duel = $state->duel;
            if ($duel === null || ! $duel->atStatisticStep()) {
                throw new DuelStepException('statistic');
            }

            match ($player->getId()) {
                $duel->challengerId => $duel->challengerStatistic = $message->getStatistic(),
                $duel->challengedId => $duel->challengedStatistic = $message->getStatistic(),
                default => throw new NotInDuelException(),
            };

            return $state;
        });

        $duel = $this->gameStateManager->getState($game)->currentDuel();
        if (! $duel->statisticsDeclared()) {
            return;
        }

        $challenger = $game->getPlayer($duel->challengerId);
        $challenged = $game->getPlayer($duel->challengedId);
        $this->honorBidService->transferHonor($challenger, $challenged);
        $challengerBid = $this->playerStateManager->getState($challenger)->getHonorBid() ?? throw new NoHonorBidException();
        $challengedBid = $this->playerStateManager->getState($challenged)->getHonorBid() ?? throw new NoHonorBidException();
        $this->gameStateManager->withState($game, function (GameState $state) use ($challengerBid, $challengedBid): GameState {
            $state->currentDuel()->determineResult($challengerBid, $challengedBid);

            return $state;
        });
    }
}
