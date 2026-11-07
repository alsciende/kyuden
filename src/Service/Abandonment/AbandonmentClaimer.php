<?php

declare(strict_types=1);

namespace App\Service\Abandonment;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Api\ApiProblemException;
use App\Message\ClaimAbandonment;
use App\Repository\GameRepository;
use App\Service\Api\PlayerMessageSender;
use App\View\Model\AbandonmentView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * A player whose opponent no longer plays ends the game, and wins (ClaimAbandonment): once the game has waited for the
 * opponent only (AwaitedPlayersResolver) for INACTIVITY seconds without changing, or for PlayerPresence::ABSENT_AFTER
 * seconds when the opponent has left it (they have not read it for as long). The player may also wait longer: nothing
 * ends the game but them. Shared by the HTTP API and the MCP tools.
 */
final readonly class AbandonmentClaimer
{
    public const int INACTIVITY = 180;

    public function __construct(
        private GameRepository $gameRepository,
        private AwaitedPlayersResolver $awaitedPlayersResolver,
        private PlayerMessageSender $messageSender,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Whom the game waits for, and from when the player may end it.
     */
    public function getView(Game $game, Player $player): AbandonmentView
    {
        $awaited = $this->awaitedPlayersResolver->resolve($game);
        $claimableAt = $this->getClaimableAt($game, $player, $awaited);

        return new AbandonmentView(
            array_map(fn (Player $awaitedPlayer): string => $awaitedPlayer->getId(), $awaited),
            $claimableAt,
            $claimableAt instanceof \DateTimeImmutable && $claimableAt <= $this->clock->now(),
        );
    }

    /**
     * Ends the game for the player of the user, their opponent having abandoned it.
     *
     * @return int the version of the game once ended
     *
     * @throws ApiProblemException
     */
    public function claim(string $gameId, string $userId): int
    {
        $game = $this->gameRepository->find($gameId) ?? throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$gameId}]");
        $player = $this->findPlayer($game, $userId) ?? throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'not_a_player', 'The user is not a player of this game.');

        $view = $this->getView($game, $player);
        if (! $view->claimable) {
            throw new ApiProblemException(Response::HTTP_CONFLICT, 'game_not_abandoned', $view->claimableAt instanceof \DateTimeImmutable
                ? \sprintf('Your opponent may still play: the game can be ended from %s if it has not changed.', $view->claimableAt->format(\DATE_ATOM))
                : 'The game does not wait for your opponent: it waits for you, or for nobody.');
        }

        // checked on the game as read now: the message is rejected if it changes meanwhile, and the lock of the game
        // reads it again (GameLockMiddleware does not reload a game already loaded)
        $version = $game->getVersion();
        $playerId = $player->getId();
        $this->entityManager->clear();

        return $this->messageSender->dispatch(new ClaimAbandonment($gameId, $playerId), $version);
    }

    public function findPlayer(Game $game, string $userId): ?Player
    {
        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $userId) {
                return $player;
            }
        }

        return null;
    }

    /**
     * Null when the game does not wait for the opponents of the player only; the latest of the times each awaited
     * opponent may be held to have stopped playing otherwise. A time to come may move away: the opponent reading the
     * game again.
     *
     * @param list<Player> $awaited
     */
    private function getClaimableAt(Game $game, Player $player, array $awaited): ?\DateTimeImmutable
    {
        $updatedAt = $game->getUpdatedAt();
        if ($updatedAt === null || $awaited === [] || in_array($player, $awaited, true)) {
            return null;
        }

        $claimableAt = $updatedAt;
        foreach ($awaited as $opponent) {
            // unchanged since, and the opponent absent since: not seen since then
            $absentSince = max($updatedAt, $opponent->getLastSeenAt() ?? $updatedAt);
            $claimableAt = max($claimableAt, min(
                $updatedAt->modify('+' . self::INACTIVITY . ' seconds'),
                $absentSince->modify('+' . PlayerPresence::ABSENT_AFTER . ' seconds'),
            ));
        }

        return $claimableAt;
    }
}
