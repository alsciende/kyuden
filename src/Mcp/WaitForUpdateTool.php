<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Controller\ApiGamesController;
use App\Service\GameUpdateWaiter;
use App\Repository\GameRepository;
use App\View\GameLogViewFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * The long-poll on the log of a game for the agents (see GET /api/games/{id}/log?since=…&wait=…): the same waiter
 * and the same log, masked for the bot.
 */
final readonly class WaitForUpdateTool
{
    public function __construct(
        private GameRepository $gameRepository,
        private GameUpdateWaiter $gameUpdateWaiter,
        private GameLogViewFactory $gameLogViewFactory,
        private NormalizerInterface $normalizer,
        private Security $security,
    ) {
    }

    /**
     * Waits until the game has changed since a version, then returns the log of the game since that version: the
     * messages handled (version, type, sender), each with what it changed, as you see it. Returns at once when the
     * game has already changed or has ended; once the timeout is over without change, returns no message. Call it
     * again with the version it returns.
     *
     * @param string $game_id the id of the game
     * @param int    $since   the version of the game you know: the log after it is returned (0 for the whole log)
     * @param int    $timeout the longest wait, in seconds
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'wait_for_update')]
    public function __invoke(
        string $game_id,
        #[Schema(minimum: 0)]
        int $since = 0,
        #[Schema(minimum: 0, maximum: ApiGamesController::MAX_WAIT)]
        int $timeout = ApiGamesController::MAX_WAIT,
    ): array {
        // the server checks the arguments against their schema (bounds included) before the call
        $game = $this->gameRepository->find($game_id);
        if ($game === null) {
            throw new ToolCallException("Game not found: [{$game_id}]");
        }

        $userId = $this->security->getUser()?->getUserIdentifier();
        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $userId) {
                $this->gameUpdateWaiter->wait($game, $since, $timeout);

                $log = $this->normalizer->normalize($this->gameLogViewFactory->create($game, $player, $since));
                \assert(\is_array($log));

                return $log;
            }
        }

        throw new ToolCallException('You are not a player of this game.');
    }
}
