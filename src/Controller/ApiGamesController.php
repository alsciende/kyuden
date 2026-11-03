<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Service\Bot\HouseBot;
use App\Service\GameUpdateWaiter;
use App\Service\Spectating\SpectatorRegistry;
use App\View\GameInProgressViewFactory;
use App\View\GameLogViewFactory;
use App\View\GameViewFactory;
use App\View\Model\SpectatorView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Discovery;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The state of a game as the authenticated player sees it (see GameViewFactory), or an account that does not play it,
 * which watches it (a spectator, see SpectatorRegistry): it only sees what is public. Guests and bots only read the
 * games they play. A read goes neither through the bus
 * nor through the lock of the game. Errors are problem details (RFC 9457).
 *
 * The ETag is the version of the game: the view only depends on the state and the viewer, so a player who sends the
 * ETag of the version they have (`If-None-Match`) gets a 304 until the game changes. So does the log of the game, from
 * a given version (`since`), masked for the player (see GameLogViewFactory).
 *
 * The view links the Mercure hub (`Link: <…>; rel="mercure"`), where the client subscribes to the topic of the game.
 *
 * Reading a game of the house bot wakes the bot, which plays once the response is sent if it has something to do: a turn
 * it has not played (its request ended before) is played when its opponent reads the game again (see HouseBot).
 *
 * The log is also a long-poll (`wait`, in seconds): the response waits until the game changes after `since`, then
 * sends what changed; if nothing has changed when the time is up, it is sent as is (no message, or a 304).
 *
 * The games in progress are listed for the accounts, to choose one to watch, and the spectators of a game for its
 * readers.
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
        private readonly HouseBot $houseBot,
        private readonly SpectatorRegistry $spectatorRegistry,
        private readonly GameInProgressViewFactory $gameInProgressViewFactory,
    ) {
    }

    #[Route('/api/games', name: 'app_api_games', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        if (! $user->canWatch()) {
            return $this->problem(Response::HTTP_FORBIDDEN, 'account_required', 'Only an account signed in to with a username watches the games.');
        }

        return $this->json($this->gameInProgressViewFactory->createAll());
    }

    #[Route('/api/games/{id}', name: 'app_api_game', methods: ['GET'])]
    public function show(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        $game = $this->gameRepository->findWithCards($id);
        if ($game === null) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$id}]");
        }

        $reader = $this->read($game, $user);
        if ($reader === false) {
            return $this->notAReader();
        }

        $this->houseBot->wake($game);
        $this->discovery->addLink($request);

        $notModified = $this->withVersion(new Response(), $game);
        if ($notModified->isNotModified($request)) {
            return $notModified;
        }

        return $this->withVersion($this->json($this->gameViewFactory->create($game, $reader)), $game);
    }

    #[Route('/api/games/{id}/log', name: 'app_api_game_log', methods: ['GET'])]
    public function log(string $id, Request $request, #[CurrentUser] User $user): Response
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

        $reader = $this->read($game, $user);
        if ($reader === false) {
            return $this->notAReader();
        }

        $this->gameUpdateWaiter->wait($game, (int) $since, (int) $wait);

        $notModified = $this->withVersion(new Response(), $game);
        if ($notModified->isNotModified($request)) {
            return $notModified;
        }

        return $this->withVersion($this->json($this->gameLogViewFactory->create($game, $reader, (int) $since)), $game);
    }

    /**
     * The accounts watching the game, in their order of arrival, for its players and its spectators.
     */
    #[Route('/api/games/{id}/spectators', name: 'app_api_game_spectators', methods: ['GET'])]
    public function spectators(string $id, #[CurrentUser] User $user): Response
    {
        $game = $this->gameRepository->find($id);
        if ($game === null) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$id}]");
        }

        if ($this->read($game, $user) === false) {
            return $this->notAReader();
        }

        $response = $this->json(array_map(
            fn (User $spectator): SpectatorView => new SpectatorView($spectator->getId(), $spectator->getName()),
            $this->spectatorRegistry->getSpectators($game),
        ));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * The authenticated account stops watching the game.
     */
    #[Route('/api/games/{id}/spectators/self', name: 'app_api_game_spectators_leave', methods: ['DELETE'])]
    public function leave(string $id, #[CurrentUser] User $user): Response
    {
        $game = $this->gameRepository->find($id);
        if ($game === null) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$id}]");
        }

        $this->spectatorRegistry->leave($game, $user);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The user reads the game: as its player, or as a spectator (null) when they may watch it (who watches it then),
     * else not at all (false). Whoever reads it removes the spectators who have stopped reading it.
     */
    private function read(Game $game, User $user): Player|false|null
    {
        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $user->getId()) {
                $this->spectatorRegistry->prune($game);

                return $player;
            }
        }

        if (! $user->canWatch()) {
            return false;
        }
        $this->spectatorRegistry->see($game, $user);

        return null;
    }

    private function notAReader(): Response
    {
        return $this->problem(Response::HTTP_FORBIDDEN, 'not_a_player', 'The user is not a player of this game, and only an account signed in to with a username watches it.');
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
