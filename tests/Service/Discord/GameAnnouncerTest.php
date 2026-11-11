<?php

declare(strict_types=1);

namespace App\Tests\Service\Discord;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\Discord\GameAnnouncer;
use App\Task\AnnounceGame;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\MessageBusInterface;

class GameAnnouncerTest extends AbstractKernelTestCase
{
    private const string WEBHOOK = 'https://discord.com/api/webhooks/1/secret';

    /**
     * @var list<array{method: string, url: string, body: mixed}> the requests sent to Discord, their body decoded
     */
    private array $requests = [];

    private function announcer(string $webhookUrl = self::WEBHOOK, int $status = 204): GameAnnouncer
    {
        $taskBus = static::getContainer()->get('task.bus');
        self::assertInstanceOf(MessageBusInterface::class, $taskBus);
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($status): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => is_string($options['body']) ? json_decode($options['body'], true) : null];

            return new MockResponse('', ['http_code' => $status]);
        });

        return new GameAnnouncer(self::service(GameRepository::class), $taskBus, $httpClient, $webhookUrl, 'https://kyuden.app/');
    }

    /**
     * @return array<string, mixed> the embed of the first message posted
     */
    private function postedEmbed(): array
    {
        /** @var array{embeds: list<array<string, mixed>>} $body */
        $body = $this->requests[0]['body'];

        return $body['embeds'][0];
    }

    private function startedGame(string $opponent = 'jane'): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $player): PlayerBuilder => $player->stronghold('01-yojin-no-shiro'))
            ->withPlayer($opponent, fn (PlayerBuilder $player): PlayerBuilder => $player->stronghold('01-shizuka-toshi'))
            ->persist();
    }

    public function testSchedulesTheAnnouncementOfAGame(): void
    {
        $game = $this->startedGame()->game();

        $this->announcer()->schedule($game);

        self::assertEquals([new AnnounceGame($game->getId())], $this->sentTasks());
    }

    public function testSchedulesNothingWithoutAWebhook(): void
    {
        $this->announcer('')->schedule($this->startedGame()->game());

        self::assertSame([], $this->sentTasks());
    }

    public function testDoesNotAnnounceAReplay(): void
    {
        $scenario = $this->startedGame();
        $game = $scenario->game();
        $game->markAsReplay(new Game('original', str_repeat('0', 32)), $scenario->player('john')->getUser());

        $this->announcer()->schedule($game);
        $this->announcer()->announce($game->getId());

        self::assertSame([], $this->sentTasks());
        self::assertSame([], $this->requests);
    }

    public function testPostsTheGameWithTheLinkToWatchIt(): void
    {
        $game = $this->startedGame()->game();

        $this->announcer()->announce($game->getId());

        self::assertSame([[
            'method' => 'POST',
            'url' => self::WEBHOOK,
            'body' => [
                'allowed_mentions' => ['parse' => []],
                'embeds' => [[
                    'title' => 'John vs Jane',
                    'url' => 'https://kyuden.app/games/' . $game->getId(),
                    'description' => 'Lion against Crane',
                ]],
            ],
        ]], $this->requests);
    }

    public function testEscapesTheMarkdownOfTheNames(): void
    {
        $scenario = $this->startedGame();
        $scenario->player('john')->getUser()->rename('*Hida* [link](x)');
        self::service(EntityManagerInterface::class)->flush();

        $this->announcer()->announce($scenario->game()->getId());

        self::assertSame('\\*Hida\\* \\[link\\]\\(x\\) vs Jane', $this->postedEmbed()['title']);
    }

    public function testTellsTheHouseBot(): void
    {
        $game = $this->startedGame('kyuden-bot')->game();

        $this->announcer()->announce($game->getId());

        self::assertSame('Lion against Crane (bot)', $this->postedEmbed()['description']);
    }

    public function testDoesNotAnnounceAGameThatHasEnded(): void
    {
        $game = $this->scenario()->atPlace(Game::STATE_ENDED)->withPlayer('john')->withPlayer('jane')->persist()->game();

        $this->announcer()->announce($game->getId());

        self::assertSame([], $this->requests);
    }

    public function testFailsWhenDiscordRefusesSoThatTheTaskIsRetried(): void
    {
        $game = $this->startedGame()->game();

        $this->expectException(\Throwable::class);
        $this->announcer(status: 500)->announce($game->getId());
    }
}
