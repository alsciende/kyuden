<?php

declare(strict_types=1);

namespace App\Tests\Service\Spectating;

use App\Entity\Game;
use App\Entity\User;
use App\Notification\NotificationInterface;
use App\Notifier\NotifierInterface;
use App\Repository\SpectatorRepository;
use App\Repository\UserRepository;
use App\Service\Spectating\SpectatorRegistry;
use App\Tests\AbstractKernelTestCase;
use Symfony\Component\Clock\MockClock;

class SpectatorRegistryTest extends AbstractKernelTestCase
{
    /**
     * @var \ArrayObject<int, NotificationInterface>
     */
    private \ArrayObject $notifications;

    private MockClock $clock;

    private function registry(): SpectatorRegistry
    {
        $this->notifications = new \ArrayObject();
        $this->clock = new MockClock('2026-11-03 12:00:00');
        $notifier = new readonly class($this->notifications) implements NotifierInterface {
            /**
             * @param \ArrayObject<int, NotificationInterface> $notifications
             */
            public function __construct(
                private \ArrayObject $notifications,
            ) {
            }

            #[\Override]
            public function notify(NotificationInterface $notification): void
            {
                $this->notifications->append($notification);
            }
        };

        return new SpectatorRegistry(self::service(SpectatorRepository::class), $notifier, $this->clock);
    }

    private function game(string $place = 'draw_phase_action_window'): Game
    {
        return $this->scenario()->atPlace($place)->withPlayer('john')->withPlayer('harry')->persist()->game();
    }

    private function user(string $id): User
    {
        $user = self::service(UserRepository::class)->find($id);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    /**
     * @return list<string>
     */
    private function names(SpectatorRegistry $registry, Game $game): array
    {
        return array_map(fn (User $user): string => $user->getName(), $registry->getSpectators($game));
    }

    public function testASpectatorWhoArrivesIsTold(): void
    {
        $registry = $this->registry();
        $game = $this->game();

        $registry->see($game, $this->user('jane'));
        $registry->see($game, $this->user('jane'));

        self::assertSame(['Jane'], $this->names($registry, $game));
        self::assertCount(1, $this->notifications);
        $notification = $this->notifications[0];
        self::assertInstanceOf(NotificationInterface::class, $notification);
        self::assertSame(['id' => $game->getId(), 'event' => 'spectators_updated'], $notification->getData());
        self::assertSame([
            '/games/' . $game->getId(),
            '/users/john/games/' . $game->getId(),
            '/users/harry/games/' . $game->getId(),
            '/users/jane/games/' . $game->getId(),
        ], $notification->getTopics());
    }

    public function testTheSpectatorsAreListedInTheirOrderOfArrival(): void
    {
        $registry = $this->registry();
        $game = $this->game();

        $registry->see($game, $this->user('jane'));
        $this->clock->sleep(1);
        $registry->see($game, $this->user('kyuden'));
        $this->clock->sleep(1);
        $registry->see($game, $this->user('jane'));

        self::assertSame(['Jane', 'Kyūden'], $this->names($registry, $game));
        self::assertSame([$game->getId() => 2], $registry->count([$game->getId()]));
    }

    public function testASpectatorWhoStopsReadingNoLongerWatches(): void
    {
        $registry = $this->registry();
        $game = $this->game();
        $registry->see($game, $this->user('jane'));

        $this->clock->sleep(SpectatorRegistry::TIMEOUT + 1);

        self::assertSame([], $this->names($registry, $game));
        self::assertSame([], $registry->count([$game->getId()]));
        $registry->prune($game);
        self::assertCount(2, $this->notifications);
        $registry->prune($game);
        self::assertCount(2, $this->notifications);
    }

    public function testASpectatorWhoLeavesIsTold(): void
    {
        $registry = $this->registry();
        $game = $this->game();
        $registry->see($game, $this->user('jane'));

        $registry->leave($game, $this->user('jane'));
        $registry->leave($game, $this->user('jane'));

        self::assertSame([], $this->names($registry, $game));
        self::assertCount(2, $this->notifications);
    }

    public function testTheSpectatorsOfAGameNobodyReadsAreRemovedLater(): void
    {
        $registry = $this->registry();
        $abandoned = $this->game();
        $registry->see($abandoned, $this->user('jane'));

        $this->clock->sleep(SpectatorRegistry::PURGE_AFTER + 1);
        $registry->see($this->game(), $this->user('jane'));

        self::assertSame(0, self::service(SpectatorRepository::class)->count(['game' => $abandoned]));
    }

    public function testNobodyWatchesAGameThatHasEnded(): void
    {
        $registry = $this->registry();
        $game = $this->game(Game::STATE_ENDED);

        $registry->see($game, $this->user('jane'));

        self::assertSame([], $this->names($registry, $game));
        self::assertCount(0, $this->notifications);
    }
}
