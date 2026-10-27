<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Game;
use App\Entity\User;
use App\Message\ConcedeGame;
use App\Repository\CandidateRepository;
use App\Repository\InvitationRepository;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * The invitations to a friendly game, and the queue with them.
 */
class ApiInvitationsControllerTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    /**
     * @param array<mixed>|null $body
     * @param string|null       $user  a user of the fixtures, or a token (`l5r_…`)
     */
    private function request(string $method, string $path, ?string $user, ?array $body = null): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $token = $user === null ? null : (UserFixtures::TOKENS[$user] ?? $user);
        $client->request($method, $path, server: array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => $token === null ? null : 'Bearer ' . $token,
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
     * @return array<mixed> the invitation created by the user
     */
    private function invite(string $user, string $kind): array
    {
        return $this->decode($this->request('POST', '/api/invitations', $user, ['kind' => $kind]), Response::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed> $invitation
     */
    private function joinQueue(string $user, array $invitation = []): Response
    {
        return $this->request('POST', '/api/queue', $user, ['deck_id' => AppFixtures::LION_STARTER_DECK, ...$invitation]);
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

    private function gameOf(string $userId): ?Game
    {
        return $this->playerRepository->findOneBy(['user' => $userId])?->getGame();
    }

    public function testAnAccountInvitesAFriend(): void
    {
        $invitation = $this->invite('john', 'friend');

        self::assertSame('friend', $invitation['kind']);
        self::assertIsString($invitation['code']);
        self::assertMatchesRegularExpression('/^[0-9A-Z]{3}-[0-9A-Z]{3}-[0-9A-Z]{3}$/', $invitation['code']);
        self::assertNull($invitation['bot']);
        self::assertNull($invitation['token']);

        // anyone with the code reads it, whatever its case and dashes
        $status = $this->decode($this->request('GET', '/api/invitations/' . strtolower(str_replace('-', '', $invitation['code'])), null), Response::HTTP_OK);
        self::assertSame(['host_name' => 'John', 'state' => 'pending'], $status);
    }

    public function testAnAccountInvitesItsBot(): void
    {
        $invitation = $this->invite('john', 'bot');

        self::assertSame('bot', $invitation['kind']);
        self::assertNull($invitation['code']);
        // the first bot of the account is reused
        self::assertSame(['user_id' => 'akodo-bot', 'name' => 'Akodo Bot'], $invitation['bot']);
        self::assertIsString($invitation['token']);
        self::assertStringStartsWith('l5r_', $invitation['token']);
    }

    public function testAnAccountWithoutBotGetsOne(): void
    {
        $invitation = $this->invite('jane', 'bot');

        self::assertIsArray($invitation['bot']);
        self::assertSame("Jane's bot", $invitation['bot']['name']);
        // the bot of the invitation reads who it is with its key
        $self = $this->decode($this->request('GET', '/api/self', $this->string($invitation['token'])), Response::HTTP_OK);
        self::assertSame($invitation['bot']['user_id'], $self['user_id']);
    }

    public function testOnlyAnAccountInvites(): void
    {
        $this->assertProblem($this->request('POST', '/api/invitations', 'harry', ['kind' => 'friend']), Response::HTTP_FORBIDDEN, 'account_required');
        $this->assertProblem($this->request('POST', '/api/invitations', 'akodo-bot', ['kind' => 'friend']), Response::HTTP_FORBIDDEN, 'account_required');
        $this->assertProblem($this->request('POST', '/api/invitations', 'john', ['kind' => 'enemy']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('POST', '/api/invitations', null, ['kind' => 'friend'])->getStatusCode());
    }

    public function testANewInvitationCancelsThePreviousOne(): void
    {
        $first = $this->invite('john', 'friend');
        $bot = $this->invite('john', 'bot');

        $this->assertProblem($this->request('GET', '/api/invitations/' . $this->string($first['code']), null), Response::HTTP_NOT_FOUND, 'invitation_not_found');
        $current = $this->decode($this->request('GET', '/api/self/invitation', 'john'), Response::HTTP_OK);
        self::assertSame($bot['id'], $current['id']);
        // the key is sent once
        self::assertNull($current['token']);
    }

    public function testTheHostCancelsTheirInvitation(): void
    {
        $invitation = $this->invite('john', 'bot');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->request('DELETE', '/api/self/invitation', 'john')->getStatusCode());

        $this->assertProblem($this->request('GET', '/api/self/invitation', 'john'), Response::HTTP_NOT_FOUND, 'invitation_not_found');
        $this->assertProblem($this->request('DELETE', '/api/self/invitation', 'john'), Response::HTTP_NOT_FOUND, 'invitation_not_found');
        // its key with it
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('GET', '/api/self', $this->string($invitation['token']))->getStatusCode());
    }

    public function testTheHostAndTheFriendAreMatchedTogetherOnly(): void
    {
        $this->emptyQueue();
        $invitation = $this->invite('john', 'friend');
        // a player waits in the queue: the host is not matched with them
        self::assertSame(Response::HTTP_NO_CONTENT, $this->joinQueue('hida-bot')->getStatusCode());
        self::assertSame(Response::HTTP_NO_CONTENT, $this->joinQueue('john', ['invitation_id' => $invitation['id']])->getStatusCode());
        self::assertNull($this->gameOf('john'));

        // a guest uses the code
        $response = $this->joinQueue('harry', ['invitation_code' => $invitation['code']]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $game = $this->gameOf('john');
        self::assertInstanceOf(Game::class, $game);
        self::assertSame($game, $this->gameOf('harry'));
        self::assertTrue($game->isFriendly());
        self::assertNull($this->gameOf('hida-bot'));
        $status = $this->decode($this->request('GET', '/api/invitations/' . $this->string($invitation['code']), null), Response::HTTP_OK);
        self::assertSame('used', $status['state']);
        $games = $this->decode($this->request('GET', '/api/self/games', 'harry'), Response::HTTP_OK);
        self::assertIsArray($games[0]);
        self::assertTrue($games[0]['friendly']);
    }

    public function testTheGuestCanComeFirst(): void
    {
        $this->emptyQueue();
        $invitation = $this->invite('john', 'friend');
        $this->joinQueue('jane', ['invitation_code' => $invitation['code']]);
        // an open candidate is not matched with the guest
        $this->joinQueue('hida-bot');
        self::assertNull($this->gameOf('jane'));

        $this->joinQueue('john', ['invitation_id' => $invitation['id']]);

        self::assertSame($this->gameOf('john'), $this->gameOf('jane'));
    }

    public function testTheFirstGuestTakesTheSeat(): void
    {
        $this->emptyQueue();
        $invitation = $this->invite('john', 'friend');
        $this->joinQueue('jane', ['invitation_code' => $invitation['code']]);

        $this->assertProblem($this->joinQueue('harry', ['invitation_code' => $invitation['code']]), Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_invitation');
    }

    public function testTheIdOfAnInvitationIsForItsHostOnly(): void
    {
        $this->emptyQueue();
        $invitation = $this->invite('john', 'friend');

        $this->assertProblem($this->joinQueue('jane', ['invitation_id' => $invitation['id']]), Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_invitation');
        $this->assertProblem($this->joinQueue('jane', ['invitation_code' => 'AAA-AAA-AAA']), Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_invitation');
    }

    public function testAnInvitationExpires(): void
    {
        $this->emptyQueue();
        $clock = self::mockTime();
        $invitation = $this->invite('john', 'friend');

        $clock->sleep(24 * 3600);

        $this->assertProblem($this->joinQueue('jane', ['invitation_code' => $invitation['code']]), Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_invitation');
        $status = $this->decode($this->request('GET', '/api/invitations/' . $this->string($invitation['code']), null), Response::HTTP_OK);
        self::assertSame('expired', $status['state']);
    }

    public function testTheHostLeavingTheQueueCancelsTheirInvitation(): void
    {
        $this->emptyQueue();
        $invitation = $this->invite('john', 'friend');
        $this->joinQueue('john', ['invitation_id' => $invitation['id']]);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->request('DELETE', '/api/queue', 'john')->getStatusCode());

        self::assertNull(self::service(InvitationRepository::class)->findUnusedOfHost($this->user('john')));
        $this->assertProblem($this->request('GET', '/api/invitations/' . $this->string($invitation['code']), null), Response::HTTP_NOT_FOUND, 'invitation_not_found');
    }

    public function testTheBotJoinsTheQueueWithItsInvitation(): void
    {
        $this->emptyQueue();
        $invitation = $this->invite('jane', 'bot');
        $token = $this->string($invitation['token']);
        $this->joinQueue('hida-bot');

        // the key joins the queue with its invitation, without saying it
        self::assertSame(Response::HTTP_NO_CONTENT, $this->joinQueue($token)->getStatusCode());
        self::assertSame(Response::HTTP_NO_CONTENT, $this->joinQueue('jane', ['invitation_id' => $invitation['id']])->getStatusCode());

        $game = $this->gameOf('jane');
        self::assertInstanceOf(Game::class, $game);
        self::assertTrue($game->isFriendly());
        self::assertIsArray($invitation['bot']);
        self::assertSame($game, $this->gameOf($this->string($invitation['bot']['user_id'])));
        self::assertNull($this->gameOf('hida-bot'));

        // the key is valid until the end of the game
        self::assertSame(Response::HTTP_OK, $this->request('GET', '/api/self', $token)->getStatusCode());
        $player = $this->playerRepository->findOneBy(['user' => 'jane']);
        self::assertNotNull($player);
        $this->messageBus->dispatch(new ConcedeGame($game->getId(), $player->getId(), 'Enough.'));
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('GET', '/api/self', $token)->getStatusCode());
    }

    public function testTheKeyOfAnInvitationExpires(): void
    {
        $clock = self::mockTime();
        $token = $this->string($this->invite('jane', 'bot')['token']);

        $clock->sleep(24 * 3600);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('GET', '/api/self', $token)->getStatusCode());
    }

    private function string(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }

    private function user(string $id): User
    {
        $user = self::service(EntityManagerInterface::class)->find(User::class, $id);
        self::assertNotNull($user);

        return $user;
    }
}
