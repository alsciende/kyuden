<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Message\JoinGame;
use App\Message\LoseHonor;
use App\Middleware\GameUpdatedNotificationMiddleware;
use App\Notification\NotificationInterface;
use App\Notifier\NotifierInterface;
use App\Repository\GameRepository;
use App\Repository\SpectatorRepository;
use App\Service\Discord\GameAnnouncer;
use App\Service\Spectating\SpectatorRegistry;
use App\Task\AnnounceGame;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\Scenario;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

class GameUpdatedNotificationMiddlewareTest extends AbstractKernelTestCase
{
    /**
     * @var \ArrayObject<int, NotificationInterface>
     */
    private \ArrayObject $notifications;

    private function middleware(string $webhookUrl = ''): GameUpdatedNotificationMiddleware
    {
        $this->notifications = new \ArrayObject();
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

        return new GameUpdatedNotificationMiddleware(
            self::service(GameRepository::class),
            $notifier,
            new SpectatorRegistry(self::service(SpectatorRepository::class), $notifier, self::service(ClockInterface::class)),
            $this->announcer($webhookUrl),
        );
    }

    private function announcer(string $webhookUrl): GameAnnouncer
    {
        $taskBus = static::getContainer()->get('task.bus');
        self::assertInstanceOf(MessageBusInterface::class, $taskBus);

        return new GameAnnouncer(self::service(GameRepository::class), $taskBus, new MockHttpClient(), $webhookUrl, 'https://kyuden.app');
    }

    private function scenarioWithJohn(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();
    }

    private function envelope(Scenario $scenario): Envelope
    {
        return new Envelope(new LoseHonor($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('john')->getId(), 1));
    }

    public function testNotifiesTheNewVersionOfTheGame(): void
    {
        $scenario = $this->scenarioWithJohn();
        $game = $scenario->game();
        $game->incrementVersion();

        $this->middleware()->handle($this->envelope($scenario), new StackMiddleware());

        self::assertCount(1, $this->notifications);
        $notification = $this->notifications[0];
        self::assertInstanceOf(NotificationInterface::class, $notification);
        self::assertSame([
            '/games/' . $game->getId(),
            '/users/john/games/' . $game->getId(),
            '/users/jane/games/' . $game->getId(),
        ], $notification->getTopics());
        self::assertSame(['id' => $game->getId(), 'version' => 1, 'event' => 'game_updated'], $notification->getData());
    }

    public function testAlsoNotifiesTheSpectators(): void
    {
        $scenario = $this->scenarioWithJohn();
        $game = $scenario->game();
        self::service(SpectatorRepository::class)->see($game->getId(), 'harry', self::service(ClockInterface::class)->now());

        $this->middleware()->handle($this->envelope($scenario), new StackMiddleware());

        $notification = $this->notifications[0];
        self::assertInstanceOf(NotificationInterface::class, $notification);
        self::assertContains('/users/harry/games/' . $game->getId(), $notification->getTopics());
    }

    public function testTellsThePlayersOnceTheLastOneHasJoined(): void
    {
        $scenario = $this->scenarioWithJohn();
        $gameId = $scenario->game()->getId();

        $this->middleware()->handle(new Envelope(new JoinGame($gameId, $scenario->player('jane')->getId(), 'jane', 'deck')), new StackMiddleware());

        self::assertCount(2, $this->notifications);
        $notification = $this->notifications[1];
        self::assertInstanceOf(NotificationInterface::class, $notification);
        self::assertSame(['/users/john', '/users/jane'], $notification->getTopics());
        self::assertSame([
            'id' => $gameId,
            'player_ids' => ['john' => $scenario->player('john')->getId(), 'jane' => $scenario->player('jane')->getId()],
            'event' => 'game_started',
        ], $notification->getData());
    }

    public function testAnnouncesTheGameOnceTheLastOneHasJoined(): void
    {
        $scenario = $this->scenarioWithJohn();
        $gameId = $scenario->game()->getId();

        $this->middleware('https://discord.com/api/webhooks/1/secret')->handle(new Envelope(new JoinGame($gameId, $scenario->player('jane')->getId(), 'jane', 'deck')), new StackMiddleware());

        self::assertEquals([new AnnounceGame($gameId)], $this->sentTasks());
    }

    public function testDoesNotTellAPlayerWhoJoinsAGameWaitingForOthers(): void
    {
        $scenario = $this->scenario()->atPlace('join_game')->withPlayer('john')->persist();
        $gameId = $scenario->game()->getId();

        $this->middleware('https://discord.com/api/webhooks/1/secret')->handle(new Envelope(new JoinGame($gameId, $scenario->player('john')->getId(), 'john', 'deck')), new StackMiddleware());

        self::assertCount(1, $this->notifications);
        self::assertSame([], $this->sentTasks());
    }

    public function testDoesNotNotifyAMessageThatFailed(): void
    {
        $scenario = $this->scenarioWithJohn();
        $failing = new class implements MiddlewareInterface {
            #[\Override]
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw new \RuntimeException('failed');
            }
        };

        try {
            $this->middleware()->handle($this->envelope($scenario), new StackMiddleware($failing));
            self::fail('The message should have failed.');
        } catch (\RuntimeException) {
        }

        self::assertCount(0, $this->notifications);
    }
}
