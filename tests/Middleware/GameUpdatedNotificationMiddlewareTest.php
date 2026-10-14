<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Message\LoseHonor;
use App\Middleware\GameUpdatedNotificationMiddleware;
use App\Notification\NotificationInterface;
use App\Notifier\NotifierInterface;
use App\Repository\GameRepository;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\Scenario;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

class GameUpdatedNotificationMiddlewareTest extends AbstractKernelTestCase
{
    /**
     * @var \ArrayObject<int, NotificationInterface>
     */
    private \ArrayObject $notifications;

    private function middleware(): GameUpdatedNotificationMiddleware
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

        return new GameUpdatedNotificationMiddleware(self::service(GameRepository::class), $notifier);
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
        self::assertSame(['/games/' . $game->getId()], $notification->getTopics());
        self::assertSame(['id' => $game->getId(), 'version' => 1, 'event' => 'game_updated'], $notification->getData());
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
