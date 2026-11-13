<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Repository\CandidateRepository;
use App\Service\Invitation\InvitationIssuer;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ApiQueueControllerTest extends AbstractKernelTestCase
{
    /**
     * @param array<mixed>|string|null $body
     */
    private function request(string $method, array|string|null $body = null, ?string $user = 'akodo-bot', string $path = '/api/queue'): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request(
            $method,
            $path,
            server: array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => $user === null ? null : 'Bearer ' . UserFixtures::TOKENS[$user],
            ]),
            content: $body === null ? null : (is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR)),
        );

        return $client->getResponse();
    }

    private function assertProblem(Response $response, int $status, string $type): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $problem = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertSame($type, $problem['type']);
    }

    /**
     * The fixtures put users in the queue: empties it.
     */
    private function emptyQueue(): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        foreach (self::service(CandidateRepository::class)->findAll() as $candidate) {
            $entityManager->remove($candidate);
        }
        $entityManager->flush();
    }

    /**
     * @return list<Player>
     */
    private function playersOf(string $userId): array
    {
        return $this->playerRepository->findBy(['user' => $userId]);
    }

    public function testAUserJoinsTheQueueWithADeck(): void
    {
        $this->emptyQueue();

        $response = $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $response = $this->request('GET');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['deck_id' => AppFixtures::LION_STARTER_DECK], json_decode((string) $response->getContent(), true));
    }

    public function testTwoCandidatesAreMatchedInAGameThatStarts(): void
    {
        $this->emptyQueue();

        $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK], 'akodo-bot');
        $response = $this->request('POST', ['deck_id' => AppFixtures::CRAB_STARTER_DECK], 'hida-bot');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, self::service(CandidateRepository::class)->count([]));
        [$akodo] = $this->playersOf('akodo-bot');
        [$hida] = $this->playersOf('hida-bot');
        self::assertSame($akodo->getGame(), $hida->getGame());
        self::assertSame(AppFixtures::LION_STARTER_DECK, $akodo->getDeck()->getId());
        self::assertSame('choose_stronghold', $akodo->getGame()->getCurrentPlace());
        self::assertTrue(self::service(GameStateManager::class)->getState($akodo->getGame())->mulligan);
        // the creation of the game, then each player joining
        self::assertSame(3, $akodo->getGame()->getVersion());
    }

    public function testTheCandidatesAreMatchedInTheirOrderOfArrival(): void
    {
        $this->emptyQueue();
        $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK], 'john');
        $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK], 'akodo-bot');

        $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK], 'jane');
        $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK], 'hida-bot');

        self::assertSame($this->playersOf('john')[0]->getGame(), $this->playersOf('akodo-bot')[0]->getGame());
        self::assertSame($this->playersOf('jane')[0]->getGame(), $this->playersOf('hida-bot')[0]->getGame());
    }

    public function testAUserJoinsTheQueueOnce(): void
    {
        $this->emptyQueue();
        $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK]);

        $response = $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK]);

        $this->assertProblem($response, Response::HTTP_CONFLICT, 'already_queued');
    }

    public function testAUserFinishesTheirGameBeforeJoiningTheQueue(): void
    {
        $this->emptyQueue();
        $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('akodo-bot')
            ->withPlayer('hida-bot')
            ->persist();

        $response = $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK]);

        $this->assertProblem($response, Response::HTTP_CONFLICT, 'game_in_progress');
        self::assertSame(0, self::service(CandidateRepository::class)->count([]));
    }

    public function testAUserWhoseGamesHaveEndedJoinsTheQueue(): void
    {
        $this->emptyQueue();
        $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('akodo-bot')
            ->withPlayer('hida-bot')
            ->persist();

        $response = $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheDeckIsOneTheUserCanUse(): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $hida = $entityManager->find(User::class, 'hida-bot');
        self::assertInstanceOf(User::class, $hida);
        $deck = new Deck('deck-of-hida');
        $deck->setOwner($hida);
        $deck->setCards(['01-banzai' => 3]);
        $entityManager->persist($deck);
        $entityManager->flush();

        $this->assertProblem($this->request('POST', ['deck_id' => 'deck-of-hida']), Response::HTTP_UNPROCESSABLE_ENTITY, 'deck_not_found');
        $this->assertProblem($this->request('POST', ['deck_id' => 'unknown']), Response::HTTP_UNPROCESSABLE_ENTITY, 'deck_not_found');
    }

    public function testTheBodyHasADeckId(): void
    {
        $this->assertProblem($this->request('POST', ['deck' => AppFixtures::LION_STARTER_DECK]), Response::HTTP_BAD_REQUEST, 'invalid_payload');
        $this->assertProblem($this->request('POST', '{'), Response::HTTP_BAD_REQUEST, 'invalid_json');
    }

    public function testAUserLeavesTheQueue(): void
    {
        $this->emptyQueue();
        $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK]);

        $response = $this->request('DELETE');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, self::service(CandidateRepository::class)->count([]));
        $this->assertProblem($this->request('GET'), Response::HTTP_NOT_FOUND, 'not_queued');
        $this->assertProblem($this->request('DELETE'), Response::HTTP_NOT_FOUND, 'not_queued');
    }

    private function waitingFor(string $user): int
    {
        $response = $this->request('GET', user: $user, path: '/api/queue/count');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $count = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($count);
        self::assertIsInt($count['waiting']);

        return $count['waiting'];
    }

    public function testTheCountTellsThePlayersWaitingForAnOpponent(): void
    {
        $this->emptyQueue();
        self::assertSame(0, $this->waitingFor('john'));

        $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK], 'akodo-bot');

        self::assertSame(1, $this->waitingFor('john'));
        // the user waiting is not counted: the next player is matched with them
        self::assertSame(0, $this->waitingFor('akodo-bot'));
    }

    public function testTheCountLeavesOutTheCandidatesOfAnInvitation(): void
    {
        $this->emptyQueue();
        $john = self::service(EntityManagerInterface::class)->find(User::class, 'john');
        self::assertInstanceOf(User::class, $john);
        [$invitation] = self::service(InvitationIssuer::class)->issue($john, InvitationKind::Friend);

        $response = $this->request('POST', ['deck_id' => AppFixtures::LION_STARTER_DECK, 'invitation_id' => $invitation->getId()], 'john');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, $this->waitingFor('jane'));
    }

    public function testTheCountRequiresAToken(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('GET', user: null, path: '/api/queue/count')->getStatusCode());
    }

    public function testTheQueueRequiresAToken(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('GET', user: null)->getStatusCode());
    }
}
