<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Deck;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\UserType;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tournaments: creating, reading, registering, cancelling.
 */
class ApiTournamentsControllerTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    private const string NOW = '2026-11-18 12:00:00';

    private MockClock $clock;

    protected function setUp(): void
    {
        $clock = self::mockTime(self::NOW);
        self::assertInstanceOf(MockClock::class, $clock);
        $this->clock = $clock;
    }

    /**
     * @param array<mixed>|null $body
     * @param string|null       $user a user of the fixtures, none for a visitor
     */
    private function request(string $method, string $path, ?string $user, ?array $body = null): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request($method, $path, server: array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => $user === null ? null : 'Bearer ' . UserFixtures::TOKENS[$user],
        ]), content: $body === null ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        return $client->getResponse();
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response, int $status): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);

        return $content;
    }

    private function assertProblem(Response $response, int $status, string $type): void
    {
        self::assertSame($type, $this->decode($response, $status)['type']);
    }

    /**
     * @return string the id of the tournament the user creates
     */
    private function create(string $user, string $startsAt): string
    {
        $id = $this->decode($this->request('POST', '/api/tournaments', $user, ['starts_at' => $startsAt]), Response::HTTP_CREATED)['id'];
        self::assertIsString($id);

        return $id;
    }

    private function register(string $tournamentId, string $user, string $deckId = AppFixtures::LION_STARTER_DECK): Response
    {
        return $this->request('PUT', "/api/self/tournaments/{$tournamentId}", $user, ['deck_id' => $deckId]);
    }

    /**
     * @return array<mixed>
     */
    private function show(string $tournamentId): array
    {
        return $this->decode($this->request('GET', "/api/tournaments/{$tournamentId}", null), Response::HTTP_OK);
    }

    public function testAnAccountCreatesATournament(): void
    {
        $response = $this->request('POST', '/api/tournaments', 'john', ['starts_at' => '2026-11-19T00:00:00Z']);

        $view = $this->decode($response, Response::HTTP_CREATED);
        self::assertSame('2026-11-19T00:00:00+00:00', $view['starts_at']);
        self::assertSame('2026-11-18T23:00:00+00:00', $view['locks_at']);
        self::assertSame('open', $view['status']);
        self::assertSame('single_elimination', $view['format']);
        self::assertSame(['user_id' => 'john', 'name' => 'John', 'banner' => ['mon' => 'enso', 'pattern' => 'none']], $view['organizer']);
        self::assertSame(Tournament::MAX_ENTRANTS, $view['max_entrants']);
        // creating it does not register its organizer
        self::assertSame([], $view['players']);
    }

    public function testTheStartMayHaveAFractionOfASecondAsTheWebClientSendsIt(): void
    {
        $id = $this->create('john', '2026-11-19T02:30:00.000+01:00');
        self::assertSame('2026-11-19T01:30:00+00:00', $this->show($id)['starts_at']);

        $this->assertProblem($this->request('POST', '/api/tournaments', 'jane', ['starts_at' => '2026-11-25T20:00:00.500Z']), Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_start');
        $this->assertProblem($this->request('POST', '/api/tournaments', 'jane', ['starts_at' => '2026-11-25T20:00:00.Z']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
    }

    public function testTheStartIsStoredInUtc(): void
    {
        $id = $this->create('john', '2026-11-19T02:30:00+01:00');

        self::assertSame('2026-11-19T01:30:00+00:00', $this->show($id)['starts_at']);
    }

    public function testATournamentBeginsOnTheHalfHourAtLeast12HoursAhead(): void
    {
        $this->assertProblem($this->request('POST', '/api/tournaments', 'john', ['starts_at' => '2026-11-19T00:15:00Z']), Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_start');
        $this->assertProblem($this->request('POST', '/api/tournaments', 'john', ['starts_at' => '2026-11-18T23:30:00Z']), Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_start');
        $this->assertProblem($this->request('POST', '/api/tournaments', 'john', ['starts_at' => '2026-11-31T20:00:00Z']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
        $this->assertProblem($this->request('POST', '/api/tournaments', 'john', ['starts_at' => 'tomorrow']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
        $this->assertProblem($this->request('POST', '/api/tournaments', 'john', ['starts_at' => '2026-11-20T20:00:00Z', 'name' => 'Winter Court']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
    }

    public function testGuestsAndBotsTakeNoPartInTournaments(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');

        foreach (['harry', 'akodo-bot'] as $user) {
            $this->assertProblem($this->request('POST', '/api/tournaments', $user, ['starts_at' => '2026-11-25T20:00:00Z']), Response::HTTP_FORBIDDEN, 'account_required');
            $this->assertProblem($this->register($id, $user), Response::HTTP_FORBIDDEN, 'account_required');
        }
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('POST', '/api/tournaments', null, ['starts_at' => '2026-11-25T20:00:00Z'])->getStatusCode());
    }

    public function testAnAccountOrganizesOneTournamentAtATime(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');

        $this->assertProblem($this->request('POST', '/api/tournaments', 'john', ['starts_at' => '2026-11-25T20:00:00Z']), Response::HTTP_CONFLICT, 'tournament_already_organized');

        $this->decode($this->request('POST', "/api/tournaments/{$id}/cancellation", 'john'), Response::HTTP_OK);
        $this->create('john', '2026-11-25T20:00:00Z');
    }

    public function testTwoTournamentsBeginAtLeast12HoursApart(): void
    {
        $this->create('john', '2026-11-20T20:00:00Z');

        $problem = $this->decode($this->request('POST', '/api/tournaments', 'jane', ['starts_at' => '2026-11-21T07:30:00Z']), Response::HTTP_CONFLICT);
        self::assertSame('tournament_too_close', $problem['type']);
        self::assertSame('2026-11-20T20:00:00+00:00', $problem['other_starts_at']);
        $this->assertProblem($this->request('POST', '/api/tournaments', 'jane', ['starts_at' => '2026-11-20T08:30:00Z']), Response::HTTP_CONFLICT, 'tournament_too_close');

        $this->create('jane', '2026-11-21T08:00:00Z');
    }

    public function testACancelledTournamentLeavesItsTimeFree(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');
        $this->decode($this->request('POST', "/api/tournaments/{$id}/cancellation", 'john'), Response::HTTP_OK);

        $this->create('jane', '2026-11-20T20:00:00Z');
    }

    public function testTheTournamentsArePublic(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');
        self::assertSame(Response::HTTP_CREATED, $this->register($id, 'jane')->getStatusCode());

        $list = $this->decode($this->request('GET', '/api/tournaments', null), Response::HTTP_OK);
        self::assertCount(1, $list);
        self::assertIsArray($list[0]);
        self::assertIsArray($list[0]['organizer']);
        self::assertSame([$id, 'open', 1, 'John'], [$list[0]['id'], $list[0]['status'], $list[0]['entrants'], $list[0]['organizer']['name']]);

        $players = $this->show($id)['players'];
        // nor their decks nor their clans
        self::assertSame([['user_id' => 'jane', 'name' => 'Jane', 'banner' => ['mon' => 'enso', 'pattern' => 'none']]], $players);

        $this->assertProblem($this->request('GET', '/api/tournaments/unknown', null), Response::HTTP_NOT_FOUND, 'tournament_not_found');
    }

    public function testAnAccountRegistersWithADeckThenChangesIt(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');

        $entry = $this->decode($this->register($id, 'jane', AppFixtures::CRANE_STARTER_DECK), Response::HTTP_CREATED);
        self::assertSame(AppFixtures::CRANE_STARTER_DECK, $entry['deck_id']);
        self::assertIsArray($entry['tournament']);
        self::assertSame([$id, 1], [$entry['tournament']['id'], $entry['tournament']['entrants']]);

        $entry = $this->decode($this->register($id, 'jane', AppFixtures::LION_STARTER_DECK), Response::HTTP_OK);
        self::assertSame(AppFixtures::LION_STARTER_DECK, $entry['deck_id']);

        $entries = $this->decode($this->request('GET', '/api/self/tournaments', 'jane'), Response::HTTP_OK);
        self::assertSame([AppFixtures::LION_STARTER_DECK], array_column($entries, 'deck_id'));
        self::assertSame([], $this->decode($this->request('GET', '/api/self/tournaments', 'john'), Response::HTTP_OK));
        self::assertIsArray($this->show($id)['players']);
        self::assertCount(1, $this->show($id)['players']);
    }

    public function testAPlayerRegistersWithADeckTheyCanUse(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');
        $entityManager = self::service(EntityManagerInterface::class);
        $john = $entityManager->find(User::class, 'john');
        $entityManager->persist(new Deck('deck-of-john')->setName('Hida')->setOwner($john)->setCards(['hida-kisada' => 1]));
        $entityManager->flush();

        $this->assertProblem($this->register($id, 'jane', 'deck-of-john'), Response::HTTP_UNPROCESSABLE_ENTITY, 'deck_not_found');
        $this->assertProblem($this->register('unknown', 'jane'), Response::HTTP_NOT_FOUND, 'tournament_not_found');
        $this->assertProblem($this->request('PUT', "/api/self/tournaments/{$id}", 'jane', ['deck' => 'x']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
    }

    public function testEverythingLocksOneHourBeforeTheStart(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');
        self::assertSame(Response::HTTP_CREATED, $this->register($id, 'jane')->getStatusCode());

        $this->clock->modify('2026-11-20 18:59:59');
        self::assertSame(Response::HTTP_OK, $this->register($id, 'jane', AppFixtures::CRANE_STARTER_DECK)->getStatusCode());

        $this->clock->modify('2026-11-20 19:00:00');
        $this->assertProblem($this->register($id, 'jane'), Response::HTTP_CONFLICT, 'tournament_closed');
        $this->assertProblem($this->register($id, 'john'), Response::HTTP_CONFLICT, 'tournament_closed');
        $this->assertProblem($this->request('DELETE', "/api/self/tournaments/{$id}", 'jane'), Response::HTTP_CONFLICT, 'tournament_closed');
        $this->assertProblem($this->request('POST', "/api/tournaments/{$id}/cancellation", 'john'), Response::HTTP_CONFLICT, 'tournament_closed');
    }

    public function testATournamentTakes64PlayersAtMost(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');
        $entityManager = self::service(EntityManagerInterface::class);
        $tournament = $entityManager->find(Tournament::class, $id);
        $deck = $entityManager->find(Deck::class, AppFixtures::LION_STARTER_DECK);
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertInstanceOf(Deck::class, $deck);
        $entityManager->persist(new TournamentEntry($tournament, $entityManager->find(User::class, 'john') ?? throw new \LogicException(), $deck, new \DateTimeImmutable(self::NOW)));
        for ($i = 2; $i <= Tournament::MAX_ENTRANTS; ++$i) {
            $user = new User("player-{$i}", "Player {$i}", UserType::Human);
            $user->register("player_{$i}", 'hash');
            $entityManager->persist($user);
            $entityManager->persist(new TournamentEntry($tournament, $user, $deck, new \DateTimeImmutable(self::NOW)));
        }
        $entityManager->flush();

        $this->assertProblem($this->register($id, 'jane'), Response::HTTP_CONFLICT, 'tournament_full');
        // a player registered changes their deck still
        self::assertSame(Response::HTTP_OK, $this->register($id, 'john', AppFixtures::CRAB_STARTER_DECK)->getStatusCode());
    }

    public function testAPlayerUnregisters(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');
        $this->register($id, 'jane');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->request('DELETE', "/api/self/tournaments/{$id}", 'jane')->getStatusCode());

        self::assertSame([], $this->show($id)['players']);
        $this->assertProblem($this->request('DELETE', "/api/self/tournaments/{$id}", 'jane'), Response::HTTP_NOT_FOUND, 'not_registered');
    }

    public function testTheOrganizerCancelsTheTournamentAndItsPlayersStayRegisteredUntilTheyUnregister(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');
        $this->register($id, 'jane');

        $this->assertProblem($this->request('POST', "/api/tournaments/{$id}/cancellation", 'jane'), Response::HTTP_FORBIDDEN, 'not_organizer');
        self::assertSame('cancelled', $this->decode($this->request('POST', "/api/tournaments/{$id}/cancellation", 'john'), Response::HTTP_OK)['status']);
        $this->assertProblem($this->request('POST', "/api/tournaments/{$id}/cancellation", 'john'), Response::HTTP_CONFLICT, 'tournament_closed');

        $this->assertProblem($this->register($id, 'john'), Response::HTTP_CONFLICT, 'tournament_closed');
        $entries = $this->decode($this->request('GET', '/api/self/tournaments', 'jane'), Response::HTTP_OK);
        self::assertSame(['cancelled'], array_map(fn (mixed $entry): mixed => is_array($entry) && is_array($entry['tournament']) ? $entry['tournament']['status'] : null, $entries));
        self::assertSame([$id], array_column($this->decode($this->request('GET', '/api/tournaments', null), Response::HTTP_OK), 'id'));

        // a cancelled tournament is left even once it would have locked
        $this->clock->modify('2026-11-20 19:30:00');
        self::assertSame(Response::HTTP_NO_CONTENT, $this->request('DELETE', "/api/self/tournaments/{$id}", 'jane')->getStatusCode());
        self::assertSame([], $this->decode($this->request('GET', '/api/self/tournaments', 'jane'), Response::HTTP_OK));
    }

    public function testACancelledTournamentIsListedUntilItWouldHaveBegun(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');
        $this->request('POST', "/api/tournaments/{$id}/cancellation", 'john');

        $this->clock->modify('2026-11-20 20:00:00');

        self::assertSame([], $this->decode($this->request('GET', '/api/tournaments', null), Response::HTTP_OK));
        self::assertSame('cancelled', $this->show($id)['status']);
    }

    public function testAnyoneAddsATournamentToTheirCalendar(): void
    {
        $id = $this->create('john', '2026-11-20T20:30:00+01:00');

        $response = $this->request('GET', "/api/tournaments/{$id}/calendar.ics", null);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename="kyuden-tournament-2026-11-20.ics"', $response->headers->get('Content-Disposition'));
        $ics = str_replace("\r\n ", '', (string) $response->getContent());
        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        self::assertStringContainsString("UID:{$id}@kyuden.app\r\n", $ics);
        self::assertStringContainsString("SUMMARY:Kyūden tournament\r\n", $ics);
        // in UTC, three hours long
        self::assertStringContainsString("DTSTART:20261120T193000Z\r\n", $ics);
        self::assertStringContainsString("DTEND:20261120T223000Z\r\n", $ics);
        self::assertStringContainsString("URL:http://localhost/tournaments/{$id}\r\n", $ics);
        self::assertStringContainsString('Organized by John.', $ics);
        // the reminder when everything locks
        self::assertStringContainsString("BEGIN:VALARM\r\n", $ics);
        self::assertStringContainsString("TRIGGER:-PT1H\r\n", $ics);
        self::assertStringNotContainsString('STATUS:CANCELLED', $ics);
    }

    public function testTheCalendarOfACancelledTournamentSaysSo(): void
    {
        $id = $this->create('john', '2026-11-20T20:00:00Z');
        $this->request('POST', "/api/tournaments/{$id}/cancellation", 'john');

        self::assertStringContainsString("STATUS:CANCELLED\r\n", (string) $this->request('GET', "/api/tournaments/{$id}/calendar.ics", null)->getContent());
    }

    public function testTheCalendarOfAnUnknownTournamentIsNotFound(): void
    {
        $this->assertProblem($this->request('GET', '/api/tournaments/unknown/calendar.ics', null), Response::HTTP_NOT_FOUND, 'tournament_not_found');
    }
}
