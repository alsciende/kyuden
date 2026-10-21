<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\GameUpdateWaiter;
use App\View\GameLogViewFactory;
use App\View\GameViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Discovery;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The state of a game as the authenticated player sees it (see GameViewFactory). A read goes neither through the bus
 * nor through the lock of the game. Errors are problem details (RFC 9457).
 *
 * The ETag is the version of the game: the view only depends on the state and the viewer, so a player who sends the
 * ETag of the version they have (`If-None-Match`) gets a 304 until the game changes. So does the log of the game, from
 * a given version (`since`), masked for the player (see GameLogViewFactory).
 *
 * The view links the Mercure hub (`Link: <…>; rel="mercure"`), where the client subscribes to the topic of the game.
 *
 * The log is also a long-poll (`wait`, in seconds): the response waits until the game changes after `since`, then
 * sends what changed; if nothing has changed when the time is up, it is sent as is (no message, or a 304).
 */
class ApiGamesController extends AbstractController
{
    use ProblemResponseTrait;

    /**
     * Longest wait of a long-poll on the log, in seconds: below the usual timeouts of HTTP clients and proxies.
     */
    public const int MAX_WAIT = 30;

    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly GameViewFactory $gameViewFactory,
        private readonly GameLogViewFactory $gameLogViewFactory,
        private readonly GameUpdateWaiter $gameUpdateWaiter,
        private readonly Discovery $discovery,
    ) {
    }

    #[Route('/api/games/{id}', name: 'app_api_game', methods: ['GET'])]
    public function show(string $id, Request $request, #[CurrentUser] UserInterface $user): Response
    {
        $game = $this->gameRepository->findWithCards($id);
        if ($game === null) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$id}]");
        }

        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $user->getUserIdentifier()) {
                $this->discovery->addLink($request);

                $notModified = $this->withVersion(new Response(), $game);
                if ($notModified->isNotModified($request)) {
                    return $notModified;
                }

                return $this->withVersion($this->json($this->gameViewFactory->create($game, $player)), $game);
            }
        }

        return $this->problem(Response::HTTP_FORBIDDEN, 'not_a_player', 'The user is not a player of this game.');
    }

    #[Route('/api/games/{id}/log', name: 'app_api_game_log', methods: ['GET'])]
    public function log(string $id, Request $request, #[CurrentUser] UserInterface $user): Response
    {
        $since = $request->query->getString('since', '0');
        if (preg_match('/^\d+$/', $since) !== 1) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_since', 'The since parameter must be a version: a natural number.');
        }

        $wait = $request->query->getString('wait', '0');
        if (preg_match('/^\d+$/', $wait) !== 1 || (int) $wait > self::MAX_WAIT) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_wait', \sprintf('The wait parameter must be a number of seconds from 0 to %d.', self::MAX_WAIT));
        }

        $game = $this->gameRepository->find($id);
        if ($game === null) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$id}]");
        }

        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $user->getUserIdentifier()) {
                $this->gameUpdateWaiter->wait($game, (int) $since, (int) $wait);

                $notModified = $this->withVersion(new Response(), $game);
                if ($notModified->isNotModified($request)) {
                    return $notModified;
                }

                return $this->withVersion($this->json($this->gameLogViewFactory->create($game, $player, (int) $since)), $game);
            }
        }

        return $this->problem(Response::HTTP_FORBIDDEN, 'not_a_player', 'The user is not a player of this game.');
    }

    /**
     * The view depends on the viewer: only the client may store it, and must check its version before reusing it.
     */
    private function withVersion(Response $response, Game $game): Response
    {
        $response->setEtag((string) $game->getVersion());
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');

        return $response;
    }
}
