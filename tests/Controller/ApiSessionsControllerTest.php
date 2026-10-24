<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Tests\AbstractKernelTestCase;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ApiSessionsControllerTest extends AbstractKernelTestCase
{
    /**
     * The attempts are counted in a cache that outlives the test: each test starts with none.
     */
    #[Before]
    protected function forgetAttempts(): void
    {
        $cache = self::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->clear();
    }

    private function request(string $method, string $uri, mixed $body = null, ?string $token = null, string $ip = '203.0.113.1'): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request(
            $method,
            $uri,
            server: array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => $token === null ? null : 'Bearer ' . $token,
                'REMOTE_ADDR' => $ip,
            ]),
            content: $body === null ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );

        return $client->getResponse();
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response, int $status): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    public function testAHumanSignsInWithTheirUsernameWhateverItsCase(): void
    {
        $session = $this->decode($this->request('POST', '/api/sessions', ['username' => 'JANE_doe', 'password' => UserFixtures::PASSWORDS['jane']]), Response::HTTP_CREATED);

        self::assertSame('jane', $session['user_id']);
        self::assertSame('Jane', $session['name']);
        self::assertSame('Jane_Doe', $session['username']);
        self::assertSame(['card_display' => 'drawn'], $session['settings']);
        self::assertIsString($session['token']);
        self::assertSame('jane', $this->decode($this->request('GET', '/api/self', token: $session['token']), Response::HTTP_OK)['user_id']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidCredentials(): iterable
    {
        yield 'wrong password' => ['john', 'not-the-password'];
        yield 'unknown username' => ['nobody', UserFixtures::PASSWORDS['john']];
        // a guest has no password
        yield 'guest' => ['harry', ''];
    }

    #[DataProvider('invalidCredentials')]
    public function testInvalidCredentialsAreRejectedAlike(string $username, string $password): void
    {
        $problem = $this->decode($this->request('POST', '/api/sessions', ['username' => $username, 'password' => $password]), Response::HTTP_UNAUTHORIZED);

        self::assertSame(['type' => 'invalid_credentials', 'status' => 401, 'detail' => 'Unknown username or wrong password.'], $problem);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'no password' => [['username' => 'john']];
        yield 'username not a string' => [['username' => 12, 'password' => 'password']];
        yield 'unexpected field' => [['username' => 'john', 'password' => 'password', 'remember' => true]];
    }

    #[DataProvider('invalidBodies')]
    public function testRejectsAnInvalidBody(mixed $body): void
    {
        self::assertSame('invalid_payload', $this->decode($this->request('POST', '/api/sessions', $body), Response::HTTP_BAD_REQUEST)['type']);
    }

    public function testTheAttemptsOnAUsernameAreLimited(): void
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            // from several addresses: the limit of the username holds whatever the address
            $this->decode($this->request('POST', '/api/sessions', ['username' => 'john', 'password' => 'guess'], ip: "203.0.113.{$attempt}"), Response::HTTP_UNAUTHORIZED);
        }

        $response = $this->request('POST', '/api/sessions', ['username' => 'JOHN', 'password' => UserFixtures::PASSWORDS['john']], ip: '203.0.113.9');
        self::assertSame('too_many_attempts', $this->decode($response, Response::HTTP_TOO_MANY_REQUESTS)['type']);
        self::assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

        // another username is not limited
        $this->decode($this->request('POST', '/api/sessions', ['username' => 'jane_doe', 'password' => UserFixtures::PASSWORDS['jane']], ip: '203.0.113.9'), Response::HTTP_CREATED);
    }

    public function testASuccessResetsTheAttemptsOnTheUsername(): void
    {
        for ($attempt = 0; $attempt < 4; ++$attempt) {
            $this->decode($this->request('POST', '/api/sessions', ['username' => 'john', 'password' => 'guess']), Response::HTTP_UNAUTHORIZED);
        }
        $this->decode($this->request('POST', '/api/sessions', ['username' => 'john', 'password' => UserFixtures::PASSWORDS['john']]), Response::HTTP_CREATED);

        for ($attempt = 0; $attempt < 4; ++$attempt) {
            $this->decode($this->request('POST', '/api/sessions', ['username' => 'john', 'password' => 'guess']), Response::HTTP_UNAUTHORIZED);
        }
    }

    public function testTheAttemptsFromAnAddressAreLimited(): void
    {
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $this->decode($this->request('POST', '/api/sessions', ['username' => "user_{$attempt}", 'password' => 'guess']), Response::HTTP_UNAUTHORIZED);
        }

        $response = $this->request('POST', '/api/sessions', ['username' => 'john', 'password' => UserFixtures::PASSWORDS['john']]);
        self::assertSame('too_many_attempts', $this->decode($response, Response::HTTP_TOO_MANY_REQUESTS)['type']);
    }

    public function testSigningOutRevokesTheSessionToken(): void
    {
        $token = $this->decode($this->request('POST', '/api/sessions', ['username' => 'john', 'password' => UserFixtures::PASSWORDS['john']]), Response::HTTP_CREATED)['token'];
        self::assertIsString($token);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->request('DELETE', '/api/sessions/current', token: $token)->getStatusCode());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('GET', '/api/self', token: $token)->getStatusCode());
        // the other sessions of the user go on
        self::assertSame(Response::HTTP_OK, $this->request('GET', '/api/self', token: UserFixtures::TOKENS['john'])->getStatusCode());
    }

    public function testAnAPIKeyIsNotRevokedBySigningOut(): void
    {
        $problem = $this->decode($this->request('DELETE', '/api/sessions/current', token: UserFixtures::TOKENS['akodo-bot']), Response::HTTP_CONFLICT);

        self::assertSame('not_a_session_token', $problem['type']);
        self::assertSame(Response::HTTP_OK, $this->request('GET', '/api/self', token: UserFixtures::TOKENS['akodo-bot'])->getStatusCode());
    }

    public function testSigningOutRequiresAToken(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('DELETE', '/api/sessions/current')->getStatusCode());
    }
}
