<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Controller\ApiGamesController;
use App\Entity\Game;
use App\Service\GameStartWaiter;
use App\View\Model\UserGameView;
use App\View\UserGameViewFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * The long-poll on the games of the bot, to learn that the queue has matched it (GameStartWaiter).
 */
final readonly class WaitForGameTool
{
    public function __construct(
        private GameStartWaiter $gameStartWaiter,
        private UserGameViewFactory $userGameViewFactory,
        private NormalizerInterface $normalizer,
        private Security $security,
    ) {
    }

    /**
     * Waits until you play a game in progress, then returns your games in progress (as list_my_games), the last
     * joined first. Returns at once when you already play one; once the timeout is over, returns no game: call it again
     * while you wait in the queue.
     *
     * @param int $timeout the longest wait, in seconds
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'wait_for_game')]
    public function __invoke(
        #[Schema(minimum: 0, maximum: ApiGamesController::MAX_WAIT)]
        int $timeout = ApiGamesController::MAX_WAIT,
    ): array {
        // the server checks the arguments against their schema (bounds included) before the call
        $userId = $this->security->getUser()?->getUserIdentifier();
        \assert($userId !== null);

        $games = $this->normalizer->normalize(array_map(
            fn (Game $game): UserGameView => $this->userGameViewFactory->create($game, $userId),
            $this->gameStartWaiter->wait($userId, $timeout),
        ));

        return ['games' => $games];
    }
}
