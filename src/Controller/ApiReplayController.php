<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\User;
use App\Exception\Data\ReplayDivergedException;
use App\Exception\Rules\GameNotEndedException;
use App\Model\GameReplay;
use App\Model\ReplayStep;
use App\Repository\GameRepository;
use App\Service\Replay\GameReplayer;
use App\View\GameLogViewFactory;
use App\View\Model\ReplayActionView;
use App\View\Model\ReplayView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function Symfony\Component\String\u;

/**
 * The replay of a game that has ended, for one of its players (GameReplayer): started (or started again) from the
 * beginning, then continued, up to the next action of the player or to a version (`{"until_version": n}`); going back
 * is starting again up to that version. Each request answers where the replay stands (ReplayView); the board is read on
 * the copy. Errors are problem details (RFC 9457).
 */
class ApiReplayController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly GameReplayer $replayer,
        private readonly GameLogViewFactory $gameLogViewFactory,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    #[Route('/api/games/{id}/replay', name: 'app_api_game_replay', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): Response
    {
        $original = $this->findOriginal($id, $user);
        if ($original instanceof Response) {
            return $original;
        }

        $replay = $this->replayer->find(...$original);
        if (! $replay instanceof GameReplay) {
            return $this->notStarted();
        }

        return $this->noStore($this->json($this->createView($replay, $this->replayer->getStep($replay))));
    }

    /**
     * The log of the original game as the player reads it, its ids translated into those of the copy: what the replay
     * has played and what it will play, told about the board of the copy.
     */
    #[Route('/api/games/{id}/replay/log', name: 'app_api_game_replay_log', methods: ['GET'])]
    public function log(string $id, #[CurrentUser] User $user): Response
    {
        $original = $this->findOriginal($id, $user);
        if ($original instanceof Response) {
            return $original;
        }

        $replay = $this->replayer->find(...$original);
        if (! $replay instanceof GameReplay) {
            return $this->notStarted();
        }

        $log = $this->normalizer->normalize($this->gameLogViewFactory->create($original[0], $original[1]), 'json');

        return $this->noStore($this->json($replay->translator->translate($log)));
    }

    #[Route('/api/games/{id}/replay', name: 'app_api_game_replay_start', methods: ['POST'])]
    public function start(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        $untilVersion = $this->readUntilVersion($request);
        if ($untilVersion instanceof Response) {
            return $untilVersion;
        }
        $original = $this->findOriginal($id, $user);
        if ($original instanceof Response) {
            return $original;
        }

        try {
            $replay = $this->replayer->start(...$original);
        } catch (GameNotEndedException) {
            return $this->problem(Response::HTTP_CONFLICT, 'game_not_ended', 'Only a game that has ended is replayed.');
        }

        return $this->proceed($replay, $untilVersion, Response::HTTP_CREATED);
    }

    #[Route('/api/games/{id}/replay/proceed', name: 'app_api_game_replay_proceed', methods: ['POST'])]
    public function continue(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        $untilVersion = $this->readUntilVersion($request);
        if ($untilVersion instanceof Response) {
            return $untilVersion;
        }
        $original = $this->findOriginal($id, $user);
        if ($original instanceof Response) {
            return $original;
        }

        $replay = $this->replayer->find(...$original);
        if (! $replay instanceof GameReplay) {
            return $this->notStarted();
        }

        $version = $this->replayer->getStep($replay)->to;
        if ($untilVersion !== null && $untilVersion < $version) {
            return $this->problem(Response::HTTP_CONFLICT, 'replay_ahead', "The replay is at version {$version}: start it again to go back.", ['version' => $version]);
        }

        return $this->proceed($replay, $untilVersion, Response::HTTP_OK);
    }

    private function proceed(GameReplay $replay, ?int $untilVersion, int $status): Response
    {
        try {
            $step = $this->replayer->proceed($replay, $untilVersion);
        } catch (ReplayDivergedException $exception) {
            return $this->problem(
                Response::HTTP_CONFLICT,
                'replay_diverged',
                sprintf('The game cannot be replayed beyond version %d: Kyūden has changed since it was played.', $exception->getVersion() - 1),
                // the copy stands at the version before the event it could not replay
                ['version' => $exception->getVersion() - 1],
            );
        }

        return $this->noStore($this->json($this->createView($replay, $step), $status));
    }

    /**
     * The game that has ended and the player of the user in it, or the problem.
     *
     * @return array{Game, Player}|Response
     */
    private function findOriginal(string $id, User $user): array|Response
    {
        $game = $this->gameRepository->find($id);
        // a replay is not replayed
        if (! $game instanceof Game || $game->isReplay()) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$id}]");
        }

        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $user->getId()) {
                return [$game, $player];
            }
        }

        return $this->problem(Response::HTTP_FORBIDDEN, 'not_a_player', 'Only a player of the game replays it.');
    }

    /**
     * The version to replay up to, from the body: null without it.
     */
    private function readUntilVersion(Request $request): int|Response|null
    {
        if ($request->getContent() === '') {
            return null;
        }
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $untilVersion = $body['until_version'] ?? null;
        if (array_diff(array_keys($body), ['until_version']) !== [] || ($untilVersion !== null && (! is_int($untilVersion) || $untilVersion < 0))) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body may only have "until_version", a version: a natural number.', [
                'violations' => [['property_path' => 'until_version', 'message' => 'The version of the game to replay up to.']],
            ]);
        }

        return $untilVersion;
    }

    private function createView(GameReplay $replay, ReplayStep $step): ReplayView
    {
        return new ReplayView(
            gameId: $replay->original->getId(),
            replayGameId: $replay->getReplayGameId(),
            playerId: $replay->getReplayViewerId(),
            version: $step->to,
            total: $replay->original->getVersion(),
            next: $step->next === null ? null : new ReplayActionView(
                $step->next->getVersion(),
                // the name of an event is the class of its message, its type the snake case short name of the class
                u($step->next->getName())->afterLast('\\')->snake()->toString(),
            ),
            ended: $step->ended,
        );
    }

    private function notStarted(): JsonResponse
    {
        return $this->problem(Response::HTTP_NOT_FOUND, 'replay_not_found', 'The user does not replay this game: start the replay.');
    }

    /**
     * Where the replay stands changes with each step.
     */
    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
