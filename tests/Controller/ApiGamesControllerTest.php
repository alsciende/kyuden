<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class ApiGamesControllerTest extends AbstractKernelTestCase
{
    private function scenarioWithHands(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->hand(['01-fine-katana']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->hand(['01-banzai']))
            ->persist();
    }

    private function get(string $gameId, ?string $user = 'john'): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request('GET', "/api/games/{$gameId}", server: array_filter([
            'HTTP_AUTHORIZATION' => $user === null ? null : "Bearer {$user}",
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
        self::assertSame([['type' => 'hand', 'count' => 1, 'codes' => ['01-fine-katana']]], $this->hands($john));
        self::assertSame([['type' => 'hand', 'count' => 1, 'codes' => null]], $this->hands($jane));
        self::assertIsArray($game['available_actions']);
        self::assertContains('bow_card', $game['available_actions']);
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
     * with a single query, whatever the number of stacks.
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
            'HTTP_AUTHORIZATION' => 'Bearer john',
        ]);

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        self::assertSame(1, $collector->getQueryCount());
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

    public function testTheGameMustExist(): void
    {
        $response = $this->get('unknown');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('game_not_found', $this->decode($response)['type']);
    }
}
