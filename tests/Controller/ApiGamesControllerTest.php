<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\DataFixtures\AppFixtures;
use App\Controller\ApiGamesController;
use App\Entity\Game;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Message\DrawConflictCards;
use App\Message\LoseHonor;
use App\Service\GameUpdateWaiter;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class ApiGamesControllerTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    private function scenarioWithHands(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->hand(['01-fine-katana']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->hand(['01-banzai']))
            ->persist();
    }

    /**
     * @param array<string, string> $headers HTTP headers of the request, by their server name (HTTP_IF_NONE_MATCH)
     */
    private function get(string $gameId, ?string $user = 'john', array $headers = [], string $path = ''): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request('GET', "/api/games/{$gameId}{$path}", server: array_filter([
            'HTTP_AUTHORIZATION' => $user === null ? null : 'Bearer ' . UserFixtures::TOKENS[$user],
            ...$headers,
        ]));

        return $client->getResponse();
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);

        return $content;
    }

    public function testThePlayerReadsTheGameAsTheySeeIt(): void
    {
        $scenario = $this->scenarioWithHands();

        $response = $this->get($scenario->game()->getId());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $game = $this->decode($response);
        self::assertSame($scenario->game()->getId(), $game['id']);
        self::assertSame('draw_phase_action_window', $game['place']);
        self::assertSame($scenario->player('john')->getId(), $game['viewer_id']);
        self::assertIsArray($game['players']);
        [$john, $jane] = $game['players'];
        self::assertIsArray($john);
        self::assertIsArray($jane);
        self::assertSame(10, $john['honor']);
        self::assertSame('John', $john['name']);
        self::assertSame([['type' => 'hand', 'count' => 1, 'codes' => ['01-fine-katana']]], $this->hands($john));
        self::assertSame([['type' => 'hand', 'count' => 1, 'codes' => null]], $this->hands($jane));
        self::assertIsArray($game['available_actions']);
        self::assertContains('bow_card', $game['available_actions']);
    }

    public function testTheViewLinksTheMercureHub(): void
    {
        $scenario = $this->scenarioWithHands();

        $response = $this->get($scenario->game()->getId());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertMatchesRegularExpression('/<[^>]+\/\.well-known\/mercure>; rel="mercure"/', (string) $response->headers->get('Link'));
    }

    public function testTheETagIsTheVersionOfTheGame(): void
    {
        $scenario = $this->scenarioWithHands();

        $response = $this->get($scenario->game()->getId());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('"0"', $response->getEtag());
        self::assertSame(0, $this->decode($response)['version']);
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
    }

    public function testThePlayerGetsNotModifiedUntilTheGameChanges(): void
    {
        $scenario = $this->scenarioWithHands();
        $gameId = $scenario->game()->getId();
        $johnId = $scenario->player('john')->getId();

        $notModified = $this->get($gameId, headers: ['HTTP_IF_NONE_MATCH' => '"0"']);
        $this->messageBus->dispatch(new LoseHonor($gameId, $johnId, $johnId, 1));
        $modified = $this->get($gameId, headers: ['HTTP_IF_NONE_MATCH' => '"0"']);

        self::assertSame(Response::HTTP_NOT_MODIFIED, $notModified->getStatusCode());
        self::assertSame('', $notModified->getContent());
        self::assertSame('"0"', $notModified->getEtag());
        self::assertSame(Response::HTTP_OK, $modified->getStatusCode());
        self::assertSame('"1"', $modified->getEtag());
    }

    /**
     * @param array<mixed> $player
     *
     * @return list<array{type: mixed, count: mixed, codes: list<mixed>|null}>
     */
    private function hands(array $player): array
    {
        self::assertIsArray($player['stacks']);
        $hands = [];
        foreach ($player['stacks'] as $stack) {
            self::assertIsArray($stack);
            if ($stack['type'] === 'hand') {
                $hands[] = [
                    'type' => $stack['type'],
                    'count' => $stack['count'],
                    'codes' => is_array($stack['cards']) ? array_column($stack['cards'], 'code') : null,
                ];
            }
        }

        return $hands;
    }

    /**
     * Performance: the view of a game well under way (full decks, cards in every stack, characters in play) is read
     * with a single query, whatever the number of stacks, besides the one authenticating the user.
     */
    public function testAGameWellUnderWayIsReadWithASingleQuery(): void
    {
        $midGame = fn (string $deckId): \Closure => fn (PlayerBuilder $p): PlayerBuilder => $p
            ->fromStarterDeck($deckId)
            ->honor(12)
            ->fate(4)
            ->hand(['01-banzai', '01-fine-katana', '01-banzai', '01-fine-katana', '01-banzai'])
            ->conflictDiscardPile(['01-banzai', '01-fine-katana', '01-hiruma-ambusher'])
            ->dynastyDiscardPile(['01-akodo-toturi', '01-imperial-storehouse'])
            ->province('01-entrenched-position', dynasty: '01-akodo-toturi', dynastyFaceup: true)
            ->province('01-entrenched-position', dynasty: '01-imperial-storehouse')
            ->province('01-ancestral-lands', dynasty: '01-master-of-the-spear', dynastyFaceup: true)
            ->province('01-entrenched-position', dynasty: '01-doji-hotaru', orientation: Orientation::UpsideDown)
            ->inPlay(['01-akodo-toturi', '01-fine-katana'], fate: 2, personalHonor: PersonalHonor::Honored)
            ->inPlay(['01-master-of-the-spear', '01-fine-katana'], bowed: true, fate: 1)
            ->inPlay(['01-doji-hotaru'], fate: 3)
            ->inPlay(['01-hiruma-ambusher'], bowed: true)
            ->inPlay(['01-vengeful-oathkeeper'], personalHonor: PersonalHonor::Dishonored);
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', $midGame(AppFixtures::LION_STARTER_DECK))
            ->withPlayer('jane', $midGame(AppFixtures::CRANE_STARTER_DECK))
            ->persist();
        // the controller must load the game from the database, not from the identity map
        self::service(EntityManagerInterface::class)->clear();
        // only count the queries of the request, not those of the setup
        self::service(Profiler::class)->reset();

        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();
        $client->enableProfiler();
        $client->request('GET', "/api/games/{$scenario->game()->getId()}", server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . UserFixtures::TOKENS['john'],
        ]);

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        // the access token with its user, then the game
        self::assertSame(2, $collector->getQueryCount());
    }

    public function testAUserWhoIsNotAPlayerOfTheGameCannotReadIt(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jack')
            ->persist();

        $response = $this->get($scenario->game()->getId(), 'jane');

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('not_a_player', $this->decode($response)['type']);
    }

    public function testAnAnonymousUserCannotReadAGame(): void
    {
        $scenario = $this->scenarioWithHands();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->get($scenario->game()->getId(), null)->getStatusCode());
    }

    public function testAnUnknownTokenIsRejected(): void
    {
        $scenario = $this->scenarioWithHands();

        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->request('GET', "/api/games/{$scenario->game()->getId()}", server: ['HTTP_AUTHORIZATION' => 'Bearer john']);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testTheGameMustExist(): void
    {
        $response = $this->get('unknown');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('game_not_found', $this->decode($response)['type']);
    }

    /**
     * John draws a card from his deck, then Jane makes him lose 1 honor.
     */
    private function scenarioWithLog(): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->conflictDeck(['01-banzai']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->persist();
        $gameId = $scenario->game()->getId();
        $johnId = $scenario->player('john')->getId();
        $this->messageBus->dispatch(new DrawConflictCards($gameId, $johnId, $johnId, 1));
        $this->messageBus->dispatch(new LoseHonor($gameId, $scenario->player('jane')->getId(), $johnId, 1));

        return $scenario;
    }

    public function testThePlayerReadsTheLogAsTheySeeIt(): void
    {
        $scenario = $this->scenarioWithLog();
        $johnId = $scenario->player('john')->getId();

        $response = $this->get($scenario->game()->getId(), 'jane', path: '/log');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('"2"', $response->getEtag());
        $log = $this->decode($response);
        self::assertSame(2, $log['version']);
        self::assertSame([
            [
                'version' => 1,
                'type' => 'draw_conflict_cards',
                'player_id' => $johnId,
                'entries' => [[
                    'template' => '{owner} draws {card}',
                    'params' => [
                        'owner' => ['type' => 'player', 'id' => $johnId],
                        'card' => ['type' => 'card', 'id' => null, 'code' => null],
                    ],
                ]],
            ],
            [
                'version' => 2,
                'type' => 'lose_honor',
                'player_id' => $scenario->player('jane')->getId(),
                'entries' => [[
                    'template' => '{player} loses {amount} honor',
                    'params' => [
                        'player' => ['type' => 'player', 'id' => $johnId],
                        'amount' => ['type' => 'value', 'value' => 1, 'hidden' => false],
                    ],
                ]],
            ],
        ], $log['messages']);
    }

    public function testThePlayerSeesTheCardsTheyKnowInTheLog(): void
    {
        $scenario = $this->scenarioWithLog();

        $log = $this->decode($this->get($scenario->game()->getId(), 'john', path: '/log'));

        self::assertIsArray($log['messages']);
        self::assertIsArray($log['messages'][0]);
        self::assertIsArray($log['messages'][0]['entries']);
        self::assertIsArray($log['messages'][0]['entries'][0]);
        self::assertIsArray($log['messages'][0]['entries'][0]['params']);
        $card = $log['messages'][0]['entries'][0]['params']['card'];
        self::assertIsArray($card);
        self::assertSame('01-banzai', $card['code']);
    }

    public function testThePlayerReadsTheLogSinceAVersion(): void
    {
        $scenario = $this->scenarioWithLog();

        $log = $this->decode($this->get($scenario->game()->getId(), 'john', path: '/log?since=1'));

        self::assertIsArray($log['messages']);
        self::assertSame([2], array_map(fn (mixed $message): mixed => is_array($message) ? $message['version'] : null, $log['messages']));
    }

    public function testTheVersionOfTheLogMustBeANaturalNumber(): void
    {
        $scenario = $this->scenarioWithLog();

        $response = $this->get($scenario->game()->getId(), 'john', path: '/log?since=-1');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('invalid_since', $this->decode($response)['type']);
    }

    public function testAUserWhoIsNotAPlayerOfTheGameCannotReadItsLog(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jack')
            ->persist();

        $response = $this->get($scenario->game()->getId(), 'jane', path: '/log');

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('not_a_player', $this->decode($response)['type']);
    }

    public function testTheGameOfTheLogMustExist(): void
    {
        $response = $this->get('unknown', path: '/log');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * Freezes the time; the callback runs the first time the waiting request sleeps, as if another request were
     * handled meanwhile.
     *
     * @param (\Closure(): void)|null $whileSleeping
     */
    private function mockClock(?\Closure $whileSleeping = null): MockClock
    {
        $mockClock = new MockClock();
        Clock::set(new class($mockClock, $whileSleeping) implements ClockInterface {
            /**
             * @param (\Closure(): void)|null $whileSleeping
             */
            public function __construct(
                private readonly MockClock $clock,
                private ?\Closure $whileSleeping,
            ) {
            }

            #[\Override]
            public function now(): \DateTimeImmutable
            {
                return $this->clock->now();
            }

            #[\Override]
            public function sleep(float|int $seconds): void
            {
                if ($this->whileSleeping !== null) {
                    ($this->whileSleeping)();
                    $this->whileSleeping = null;
                }

                $this->clock->sleep($seconds);
            }

            #[\Override]
            public function withTimeZone(\DateTimeZone|string $timezone): static
            {
                return $this;
            }
        });

        return $mockClock;
    }

    /**
     * Seconds the waiting request has slept since the clock was mocked.
     */
    private function elapsed(MockClock $clock, \DateTimeImmutable $start): float
    {
        return round((float) $clock->now()->format('U.u') - (float) $start->format('U.u'), 3);
    }

    public function testTheLogWaitsUntilTheGameChanges(): void
    {
        $scenario = $this->scenarioWithLog();
        $gameId = $scenario->game()->getId();
        $johnId = $scenario->player('john')->getId();
        $clock = $this->mockClock(function () use ($gameId, $johnId): void {
            $this->messageBus->dispatch(new LoseHonor($gameId, $johnId, $johnId, 1));
        });
        $start = $clock->now();

        $response = $this->get($gameId, 'john', path: '/log?since=2&wait=30');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('"3"', $response->getEtag());
        $log = $this->decode($response);
        self::assertSame(3, $log['version']);
        self::assertIsArray($log['messages']);
        self::assertSame([3], array_map(fn (mixed $message): mixed => is_array($message) ? $message['version'] : null, $log['messages']));
        self::assertSame(GameUpdateWaiter::POLL_INTERVAL, $this->elapsed($clock, $start));
    }

    public function testTheLogIsSentAtOnceWhenTheGameHasChangedSinceTheVersion(): void
    {
        $scenario = $this->scenarioWithLog();
        $clock = $this->mockClock();
        $start = $clock->now();

        $response = $this->get($scenario->game()->getId(), 'john', path: '/log?since=1&wait=30');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('"2"', $response->getEtag());
        self::assertSame(0.0, $this->elapsed($clock, $start));
    }

    public function testTheLogIsSentWithoutMessageWhenTheGameHasNotChangedInTime(): void
    {
        $scenario = $this->scenarioWithLog();
        $clock = $this->mockClock();
        $start = $clock->now();

        $response = $this->get($scenario->game()->getId(), 'john', path: '/log?since=2&wait=5');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('"2"', $response->getEtag());
        self::assertSame([], $this->decode($response)['messages']);
        self::assertSame(5.0, $this->elapsed($clock, $start));
    }

    public function testThePlayerGetsNotModifiedWhenTheGameHasNotChangedInTime(): void
    {
        $scenario = $this->scenarioWithLog();
        $this->mockClock();

        $response = $this->get($scenario->game()->getId(), 'john', ['HTTP_IF_NONE_MATCH' => '"2"'], '/log?since=2&wait=5');

        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    public function testTheLogOfAGameThatHasEndedDoesNotWait(): void
    {
        $scenario = $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();
        $clock = $this->mockClock();
        $start = $clock->now();

        $response = $this->get($scenario->game()->getId(), 'john', path: '/log?wait=30');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(0.0, $this->elapsed($clock, $start));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidWaits(): iterable
    {
        yield 'negative' => ['-1'];
        yield 'not a number' => ['soon'];
        yield 'too long' => [(string) (ApiGamesController::MAX_WAIT + 1)];
    }

    #[DataProvider('invalidWaits')]
    public function testTheWaitMustBeABoundedNumberOfSeconds(string $wait): void
    {
        $scenario = $this->scenarioWithLog();

        $response = $this->get($scenario->game()->getId(), 'john', path: "/log?wait={$wait}");

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('invalid_wait', $this->decode($response)['type']);
    }
}
