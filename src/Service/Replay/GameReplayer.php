<?php

declare(strict_types=1);

namespace App\Service\Replay;

use App\Entity\Event;
use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\ReplayDivergedException;
use App\Exception\Rules\GameNotEndedException;
use App\Message\PlayerActionInterface;
use App\Middleware\Stamp\ReplayStamp;
use App\Model\GameReplay;
use App\Model\ReplayIdTranslator;
use App\Model\ReplayStep;
use App\Repository\EventRepository;
use App\Repository\GameLogEntryRepository;
use App\Repository\GameRepository;
use App\Service\ActionMessageRegistry;
use App\Service\EventPlayer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Replays a game that has ended for one of its players: its events are dispatched again, their ids translated, on a
 * copy of the game under another id (ReplayIdTranslator), which the seed of the original makes draw the same values.
 * The copy is marked as the replay of the original for the player's user (Game::markAsReplay()) once it is created,
 * and kept between two steps. The replay stops before each event of the player, so that they see the board as they
 * had to decide. The messages replayed carry a ReplayStamp: nobody is notified, the house bot does not play, and no
 * other message is accepted on the copy (GameReplayMiddleware).
 */
readonly class GameReplayer
{
    public function __construct(
        private EventPlayer $eventPlayer,
        private EventRepository $eventRepository,
        private GameRepository $gameRepository,
        private GameLogEntryRepository $gameLogEntryRepository,
        private ActionMessageRegistry $actionMessageRegistry,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * A new replay of the game for the player, nothing replayed yet: the replay they followed is deleted.
     *
     * @throws GameNotEndedException
     */
    public function start(Game $original, Player $viewer): GameReplay
    {
        if ($original->getCurrentPlace() !== Game::STATE_ENDED) {
            throw new GameNotEndedException();
        }

        $previous = $this->gameRepository->findReplay($original->getId(), $viewer->getUserId());
        if ($previous instanceof Game) {
            $this->gameRepository->delete($previous->getId());
        }

        return new GameReplay($original, $viewer, new ReplayIdTranslator($original->getId(), Uuid::v4()->toString()));
    }

    /**
     * The replay of the game the player follows, if any.
     */
    public function find(Game $original, Player $viewer): ?GameReplay
    {
        $copy = $this->gameRepository->findReplay($original->getId(), $viewer->getUserId());

        return $copy instanceof Game ? new GameReplay($original, $viewer, new ReplayIdTranslator($original->getId(), $copy->getId())) : null;
    }

    /**
     * Replays the events after the version of the replay: the next one whatever its sender (the action of the player
     * the replay stopped before), then those up to the next event of the player, excluded, or to the end; up to the
     * version given instead, whoever sends the events.
     *
     * @throws ReplayDivergedException when an event cannot be replayed
     */
    public function proceed(GameReplay $replay, ?int $untilVersion = null): ReplayStep
    {
        $from = $this->getVersion($replay);
        if ($untilVersion !== null && $untilVersion < $from) {
            throw new \InvalidArgumentException("The replay is at version {$from}, after version {$untilVersion}: start it again.");
        }

        $events = $this->eventRepository->findSince($replay->original, $from);
        $first = true;
        while (($event = array_shift($events)) instanceof Event) {
            $stops = $untilVersion === null ? ! $first && $this->isSentBy($event, $replay->viewer) : $event->getVersion() > $untilVersion;
            if ($stops) {
                array_unshift($events, $event);
                break;
            }
            $this->play($replay, $event);
            $first = false;
        }

        return $this->describe($replay, $from, $events);
    }

    /**
     * Where the replay stands, nothing replayed.
     */
    public function getStep(GameReplay $replay): ReplayStep
    {
        $version = $this->getVersion($replay);

        return $this->describe($replay, $version, $this->eventRepository->findSince($replay->original, $version));
    }

    /**
     * @param list<Event> $events the events left to replay
     */
    private function describe(GameReplay $replay, int $from, array $events): ReplayStep
    {
        $next = array_find($events, fn (Event $event): bool => $this->isSentBy($event, $replay->viewer));

        return new ReplayStep($from, $this->getVersion($replay), $next, $events === []);
    }

    /**
     * The game of the replay, once its first event is replayed.
     */
    public function getReplayGame(GameReplay $replay): ?Game
    {
        return $this->gameRepository->find($replay->getReplayGameId());
    }

    /**
     * The first version whose log differs between the original and the replay (ids translated), null when they are
     * the same: the replay rebuilds the game as it was.
     */
    public function findDivergence(GameReplay $replay): ?int
    {
        $game = $this->getReplayGame($replay);
        $expected = $this->entriesByVersion($replay->original, $replay->translator);
        $actual = $game instanceof Game ? $this->entriesByVersion($game) : [];

        $versions = [$replay->original->getVersion(), $game?->getVersion() ?? 0];
        for ($version = 1; $version <= max($versions); ++$version) {
            if (($expected[$version] ?? null) !== ($actual[$version] ?? null)) {
                return $version;
            }
        }
        if ($versions[0] !== $versions[1]) {
            return min($versions) + 1;
        }
        if ($game?->getCurrentPlace() !== $replay->original->getCurrentPlace()) {
            return $replay->original->getVersion();
        }

        return null;
    }

    /**
     * @throws ReplayDivergedException
     */
    private function play(GameReplay $replay, Event $event): void
    {
        try {
            $this->eventPlayer->play($event->getName(), $replay->translator->translatePayload($event->getPayload()), new ReplayStamp());
        } catch (\Throwable $exception) {
            throw new ReplayDivergedException($event->getVersion(), $exception);
        }

        // the copy has just been created (CreateGame)
        $copy = $this->getReplayGame($replay);
        if ($copy instanceof Game && ! $copy->isReplay()) {
            $copy->markAsReplay($replay->original, $replay->viewer->getUser());
            $this->entityManager->flush();
        }
    }

    private function getVersion(GameReplay $replay): int
    {
        return $this->getReplayGame($replay)?->getVersion() ?? 0;
    }

    /**
     * An action the viewer sent themselves: a public message (not the platform's) whose sender is the viewer.
     */
    private function isSentBy(Event $event, Player $viewer): bool
    {
        // the name of an event is the class of its message
        $class = $event->getName();
        if (! is_a($class, PlayerActionInterface::class, true) || ! $this->actionMessageRegistry->getAttribute($class)->public) {
            return false;
        }
        $payload = json_decode($event->getPayload(), true, flags: JSON_THROW_ON_ERROR);

        return is_array($payload) && ($payload['player_id'] ?? null) === $viewer->getId();
    }

    /**
     * The log of a game, its ids translated when given a translator.
     *
     * @return array<int, list<array{string, mixed}>> template and params of each entry, by version
     */
    private function entriesByVersion(Game $game, ?ReplayIdTranslator $translator = null): array
    {
        $entries = [];
        foreach ($this->gameLogEntryRepository->findSince($game) as $entry) {
            $params = $entry->getParams();
            $entries[$entry->getVersion()][] = [$entry->getTemplate(), $this->canonical($translator instanceof ReplayIdTranslator ? $translator->translate($params) : $params)];
        }

        return $entries;
    }

    /**
     * The params of an entry with their keys sorted: jsonb does not keep their order, an entry not read again does.
     */
    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $value = array_map($this->canonical(...), $value);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
