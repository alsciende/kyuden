<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Tests\AbstractKernelTestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use PHPUnit\Framework\Attributes\Before;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * The providers are enabled in the tests (`.env.test`) and answer what the tests queue in the MockHandler of their
 * HTTP client.
 */
class ApiOAuthControllerTest extends AbstractKernelTestCase
{
    private MockHandler $provider;

    /** @var list<RequestInterface> the requests sent to the providers */
    private array $providerRequests = [];

    #[Before]
    protected function forgetAttempts(): void
    {
        $cache = self::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->clear();

        $provider = self::getContainer()->get(MockHandler::class);
        self::assertInstanceOf(MockHandler::class, $provider);
        $this->provider = $provider;
    }

    private function request(string $method, string $uri, mixed $body = null, ?string $user = null): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request(
            $method,
            $uri,
            server: array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => $user === null ? null : 'Bearer ' . UserFixtures::TOKENS[$user],
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

    private function assertProblem(Response $response, int $status, string $type): void
    {
        self::assertSame($type, $this->decode($response, $status)['type']);
    }

    /**
     * Starts a flow: the state the provider will send back.
     */
    private function authorize(string $provider, string $purpose, ?string $user = null): string
    {
        $authorization = $this->decode($this->request('POST', "/api/oauth/{$provider}/authorizations", ['purpose' => $purpose], $user), Response::HTTP_CREATED);
        self::assertIsString($authorization['state']);

        return $authorization['state'];
    }

    /**
     * The provider exchanges the code for a token, then gives the identity.
     *
     * @param array<string, mixed> $identity
     */
    private function providerAnswers(array $identity): void
    {
        $record = function (RequestInterface $request, Psr7Response $response): Psr7Response {
            $this->providerRequests[] = $request;

            return $response;
        };
        $this->provider->append(
            static fn (RequestInterface $request): Psr7Response => $record($request, new Psr7Response(200, ['Content-Type' => 'application/json'], '{"access_token": "provider-token", "token_type": "bearer", "expires_in": 3600}')),
            static fn (RequestInterface $request): Psr7Response => $record($request, new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode($identity, \JSON_THROW_ON_ERROR))),
        );
    }

    private function link(string $user, string $provider, string $subject): Response
    {
        $state = $this->authorize($provider, 'link', $user);
        $this->providerAnswers($provider === 'google' ? ['sub' => $subject] : ['id' => $subject, 'username' => 'someone']);

        return $this->request('PUT', "/api/self/linked-accounts/{$provider}", ['code' => 'the-code', 'state' => $state], $user);
    }

    private function signIn(string $provider, string $subject): Response
    {
        $state = $this->authorize($provider, 'sign_in');
        $this->providerAnswers($provider === 'google' ? ['sub' => $subject] : ['id' => $subject, 'username' => 'someone']);

        return $this->request('POST', "/api/oauth/{$provider}/sessions", ['code' => 'the-code', 'state' => $state]);
    }

    public function testTheEnabledProvidersAreListed(): void
    {
        self::assertSame(['discord', 'google'], $this->decode($this->request('GET', '/api/oauth/providers'), Response::HTTP_OK));
    }

    public function testTheHumanIsSentToTheProviderWithAStateAndACodeChallenge(): void
    {
        $authorization = $this->decode($this->request('POST', '/api/oauth/google/authorizations', ['purpose' => 'sign_in']), Response::HTTP_CREATED);

        self::assertIsString($authorization['authorization_url']);
        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $authorization['authorization_url']);
        parse_str((string) parse_url($authorization['authorization_url'], \PHP_URL_QUERY), $query);
        self::assertSame($authorization['state'], $query['state']);
        self::assertSame('test-google-client', $query['client_id']);
        self::assertSame('http://localhost/oauth/google/callback', $query['redirect_uri']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('openid', $query['scope']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertIsString($query['code_challenge']);
    }

    public function testAnAccountLinkedToGoogleSignsInWithIt(): void
    {
        $state = $this->authorize('google', 'link', 'jane');
        $this->providerAnswers(['sub' => 'google-jane']);
        $linked = $this->decode($this->request('PUT', '/api/self/linked-accounts/google', ['code' => 'the-code', 'state' => $state], 'jane'), Response::HTTP_CREATED);
        self::assertSame('google', $linked['provider']);

        // the code is exchanged with the secret and the code verifier
        parse_str((string) $this->providerRequests[0]->getBody(), $tokenRequest);
        self::assertSame('authorization_code', $tokenRequest['grant_type']);
        self::assertSame('the-code', $tokenRequest['code']);
        self::assertSame('test-google-secret', $tokenRequest['client_secret']);
        self::assertSame('http://localhost/oauth/google/callback', $tokenRequest['redirect_uri']);
        self::assertIsString($tokenRequest['code_verifier']);

        $session = $this->decode($this->signIn('google', 'google-jane'), Response::HTTP_CREATED);
        self::assertSame('jane', $session['user_id']);
        self::assertSame('jane', $session['username']);
        self::assertIsString($session['token']);

        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->request('GET', '/api/self', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $session['token']]);
        self::assertSame('jane', $this->decode($client->getResponse(), Response::HTTP_OK)['user_id']);
    }

    public function testAnIdentityLinkedToNoAccountDoesNotSignIn(): void
    {
        $this->assertProblem($this->signIn('google', 'google-nobody'), Response::HTTP_UNAUTHORIZED, 'not_linked');
    }

    public function testAnAccountLinkedToDiscordSignsInWithIt(): void
    {
        $this->decode($this->link('john', 'discord', 'discord-john'), Response::HTTP_CREATED);

        [$tokenRequest, $identityRequest] = $this->providerRequests;
        self::assertSame('https://discord.com/api/v9/oauth2/token', (string) $tokenRequest->getUri());
        parse_str((string) $tokenRequest->getBody(), $tokenParams);
        self::assertSame('test-discord-secret', $tokenParams['client_secret']);
        self::assertIsString($tokenParams['code_verifier']);
        self::assertSame('https://discord.com/api/v9/users/@me', (string) $identityRequest->getUri());
        self::assertSame('Bearer provider-token', $identityRequest->getHeaderLine('Authorization'));

        self::assertSame('john', $this->decode($this->signIn('discord', 'discord-john'), Response::HTTP_CREATED)['user_id']);
    }

    public function testAStateIsUsedOnce(): void
    {
        $state = $this->authorize('google', 'link', 'jane');
        $this->providerAnswers(['sub' => 'google-jane']);
        $this->decode($this->request('PUT', '/api/self/linked-accounts/google', ['code' => 'the-code', 'state' => $state], 'jane'), Response::HTTP_CREATED);

        $this->assertProblem($this->request('PUT', '/api/self/linked-accounts/google', ['code' => 'the-code', 'state' => $state], 'jane'), Response::HTTP_BAD_REQUEST, 'invalid_state');
    }

    public function testAStateIsUsedForWhatItWasIssued(): void
    {
        $this->assertProblem($this->request('POST', '/api/oauth/google/sessions', ['code' => 'the-code', 'state' => 'unknown']), Response::HTTP_BAD_REQUEST, 'invalid_state');
        // to link, not to sign in
        $this->assertProblem($this->request('POST', '/api/oauth/google/sessions', ['code' => 'the-code', 'state' => $this->authorize('google', 'link', 'jane')]), Response::HTTP_BAD_REQUEST, 'invalid_state');
        // to link the account of jane, not that of john
        $this->assertProblem($this->request('PUT', '/api/self/linked-accounts/google', ['code' => 'the-code', 'state' => $this->authorize('google', 'link', 'jane')], 'john'), Response::HTTP_BAD_REQUEST, 'invalid_state');
        // to sign in, not to link
        $this->assertProblem($this->request('PUT', '/api/self/linked-accounts/google', ['code' => 'the-code', 'state' => $this->authorize('google', 'sign_in')], 'jane'), Response::HTTP_BAD_REQUEST, 'invalid_state');
        // for Google, not for Discord
        $this->assertProblem($this->request('PUT', '/api/self/linked-accounts/discord', ['code' => 'the-code', 'state' => $this->authorize('google', 'link', 'jane')], 'jane'), Response::HTTP_BAD_REQUEST, 'invalid_state');

        self::assertSame([], $this->providerRequests);
    }

    public function testOnlyAnAccountIsLinked(): void
    {
        $this->assertProblem($this->request('POST', '/api/oauth/google/authorizations', ['purpose' => 'link']), Response::HTTP_UNAUTHORIZED, 'authentication_required');
        $this->assertProblem($this->request('POST', '/api/oauth/google/authorizations', ['purpose' => 'link'], 'harry'), Response::HTTP_FORBIDDEN, 'account_required');
        $this->assertProblem($this->request('POST', '/api/oauth/google/authorizations', ['purpose' => 'link'], 'akodo-bot'), Response::HTTP_FORBIDDEN, 'account_required');
        $this->assertProblem($this->request('PUT', '/api/self/linked-accounts/google', ['code' => 'the-code', 'state' => 'any'], 'harry'), Response::HTTP_FORBIDDEN, 'account_required');
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('PUT', '/api/self/linked-accounts/google', ['code' => 'the-code', 'state' => 'any'])->getStatusCode());
    }

    public function testAnIdentityIsLinkedToOneAccountAndAnAccountToOneIdentityOfEachProvider(): void
    {
        $this->decode($this->link('jane', 'google', 'google-jane'), Response::HTTP_CREATED);

        // the same identity again: nothing changes
        $this->decode($this->link('jane', 'google', 'google-jane'), Response::HTTP_OK);
        $this->assertProblem($this->link('jane', 'google', 'google-other'), Response::HTTP_CONFLICT, 'provider_already_linked');
        $this->assertProblem($this->link('john', 'google', 'google-jane'), Response::HTTP_CONFLICT, 'identity_already_linked');
        // another provider
        $this->decode($this->link('jane', 'discord', 'discord-jane'), Response::HTTP_CREATED);

        $linked = $this->decode($this->request('GET', '/api/self/linked-accounts', user: 'jane'), Response::HTTP_OK);
        self::assertSame(['discord', 'google'], array_column($linked, 'provider'));
        self::assertSame([], $this->decode($this->request('GET', '/api/self/linked-accounts', user: 'john'), Response::HTTP_OK));
    }

    public function testAnUnlinkedIdentityNoLongerSignsIn(): void
    {
        $this->decode($this->link('jane', 'google', 'google-jane'), Response::HTTP_CREATED);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->request('DELETE', '/api/self/linked-accounts/google', user: 'jane')->getStatusCode());
        $this->assertProblem($this->request('DELETE', '/api/self/linked-accounts/google', user: 'jane'), Response::HTTP_NOT_FOUND, 'not_linked');
        $this->assertProblem($this->signIn('google', 'google-jane'), Response::HTTP_UNAUTHORIZED, 'not_linked');
        // linked again, to another account
        $this->decode($this->link('john', 'google', 'google-jane'), Response::HTTP_CREATED);
    }

    public function testACodeRefusedByTheProviderFails(): void
    {
        $state = $this->authorize('google', 'sign_in');
        $this->provider->append(new Psr7Response(400, ['Content-Type' => 'application/json'], '{"error": "invalid_grant", "error_description": "Bad Request"}'));

        $this->assertProblem($this->request('POST', '/api/oauth/google/sessions', ['code' => 'the-code', 'state' => $state]), Response::HTTP_BAD_REQUEST, 'authorization_failed');
    }

    public function testAProviderOutOfReachIsABadGateway(): void
    {
        $state = $this->authorize('google', 'sign_in');
        $this->provider->append(new ConnectException('Connection refused', new Psr7Request('POST', 'https://oauth2.googleapis.com/token')));

        $this->assertProblem($this->request('POST', '/api/oauth/google/sessions', ['code' => 'the-code', 'state' => $state]), Response::HTTP_BAD_GATEWAY, 'provider_unavailable');
    }

    public function testAnUnknownProviderIsNotFound(): void
    {
        $this->assertProblem($this->request('POST', '/api/oauth/nowhere/authorizations', ['purpose' => 'sign_in']), Response::HTTP_NOT_FOUND, 'unknown_provider');
        $this->assertProblem($this->request('POST', '/api/oauth/nowhere/sessions', ['code' => 'the-code', 'state' => 'any']), Response::HTTP_NOT_FOUND, 'unknown_provider');
    }

    public function testTheBodiesAreChecked(): void
    {
        $this->assertProblem($this->request('POST', '/api/oauth/google/authorizations', ['purpose' => 'register']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
        $this->assertProblem($this->request('POST', '/api/oauth/google/authorizations', ['purpose' => 'sign_in', 'user' => 'jane']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
        $this->assertProblem($this->request('POST', '/api/oauth/google/sessions', ['code' => 'the-code']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
        $this->assertProblem($this->request('POST', '/api/oauth/google/sessions', ['code' => 1, 'state' => 'any']), Response::HTTP_BAD_REQUEST, 'invalid_payload');
    }
}
