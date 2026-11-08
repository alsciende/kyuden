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
use Symfony\Component\Uid\Uuid;

/**
 * Replays a game that has ended for one of its players: its events are dispatched again, their ids translated, on a
 * copy of the game under another id (ReplayIdTranslator), which the seed of the original makes draw the same values.
 * The replay stops on each event of the viewer, so that they follow the game from one of their actions to the next.
 * The messages replayed carry a ReplayStamp: nobody is notified and the house bot does not play.
 */
readonly class GameReplayer
{
    public function __construct(
        private EventPlayer $eventPlayer,
        private EventRepository $eventRepository,
        private GameRepository $gameRepository,
        private GameLogEntryRepository $gameLogEntryRepository,
        private ActionMessageRegistry $actionMessageRegistry,
    ) {
    }

    /**
     * A new replay of the game, nothing replayed yet.
     *
     * @throws GameNotEndedException
     */
    public function start(Game $original, Player $viewer): GameReplay
    {
        if ($original->getCurrentPlace() !== Game::STATE_ENDED) {
            throw new GameNotEndedException();
        }

        return new GameReplay($original, $viewer, new ReplayIdTranslator($original->getId(), Uuid::v4()->toString()));
    }

    /**
     * Replays the events after the version of the replay, up to the next event of the viewer included, or to the end.
     *
     * @throws ReplayDivergedException when an event cannot be replayed
     */
    public function proceed(GameReplay $replay): ReplayStep
    {
        $from = $this->getVersion($replay);
        $events = $this->eventRepository->findSince($replay->original, $from);
        $pausedOn = null;
        while (($event = array_shift($events)) instanceof Event) {
            try {
                $this->eventPlayer->play($event->getName(), $replay->translator->translatePayload($event->getPayload()), new ReplayStamp());
            } catch (\Throwable $exception) {
                throw new ReplayDivergedException($event->getVersion(), $exception);
            }
            if ($this->isSentBy($event, $replay->viewer)) {
                $pausedOn = $event;
                break;
            }
        }

        // ended once no event is left to replay
        return new ReplayStep($from, $this->getVersion($replay), $pausedOn, $events === []);
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
