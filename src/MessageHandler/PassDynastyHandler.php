<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Rules\NotActivePlayerException;
use App\Message\PassDynasty;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\OpportunityService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The first player to pass gains 1 fate. The others keep acting, in player order, until every player has passed.
 */
#[AsMessageHandler]
final readonly class PassDynastyHandler
{
    public const int FIRST_TO_PASS_FATE = 1;

    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $stateManager,
        private OpportunityService $opportunityService,
    ) {
    }

    public function __invoke(PassDynasty $message): void
    {
        $game = $this->gameRepository->get($message->getGameId(), 'play_cards_from_provinces');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }

        $firstToPass = ! $this->hasAnyPlayerPassed($game);
        $this->stateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state
                ->incrementFate($firstToPass ? self::FIRST_TO_PASS_FATE : 0)
                ->setPassed(true),
        );

        $this->opportunityService->passToNextPlayer($game, $player);
    }

    private function hasAnyPlayerPassed(Game $game): bool
    {
        return $game->getPlayers()->exists(
            fn (int $key, Player $player): bool => $this->stateManager->getState($player)->isPassed(),
        );
    }
}
