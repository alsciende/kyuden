<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\Game;
use App\Entity\User;
use App\Tests\AbstractKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ApiSelfControllerTest extends AbstractKernelTestCase
{
    private function get(string $path, ?string $user): Response
    {
        return $this->request('GET', $path, $user);
    }

    private function request(string $method, string $path, ?string $user, mixed $body = null): Response
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
    private function decodeProblem(Response $response, int $status): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);

        return $content;
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);

        return $content;
    }

    public function testAUserReadsTheirIdentifierNameAndUsername(): void
    {
        self::assertSame(['user_id' => 'jane', 'name' => 'Jane', 'username' => 'jane', 'preferred_clan' => 'crane', 'settings' => ['card_display' => 'images', 'theme' => 'auto'], 'banner' => ['mon' => 'enso', 'pattern' => 'none'], 'unlocked' => ['mons' => ['enso'], 'patterns' => ['none']]], $this->decode($this->get('/api/self', 'jane')));
    }

    public function testAGuestHasNoUsername(): void
    {
        self::assertSame(['user_id' => 'harry', 'name' => 'Harry', 'username' => null, 'preferred_clan' => null, 'settings' => ['card_display' => 'images', 'theme' => 'auto'], 'banner' => ['mon' => 'enso', 'pattern' => 'none'], 'unlocked' => ['mons' => ['enso'], 'patterns' => ['none']]], $this->decode($this->get('/api/self', 'harry')));
    }

    public function testTheSelfRequiresAToken(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->get('/api/self', null)->getStatusCode());
    }

    public function testAUserChangesTheirNameAtCourt(): void
    {
        $response = $this->request('PATCH', '/api/self', 'jane', ['name' => '  Doji Hotaru ']);

        self::assertSame(['user_id' => 'jane', 'name' => 'Doji Hotaru', 'username' => 'jane', 'preferred_clan' => 'crane', 'settings' => ['card_display' => 'images', 'theme' => 'auto'], 'banner' => ['mon' => 'enso', 'pattern' => 'none'], 'unlocked' => ['mons' => ['enso'], 'patterns' => ['none']]], $this->decode($response));
        self::assertSame('Doji Hotaru', $this->decode($this->get('/api/self', 'jane'))['name']);
    }

    public function testAUserChangesTheirPreferredClan(): void
    {
        $response = $this->request('PATCH', '/api/self', 'jane', ['preferred_clan' => 'phoenix']);

        self::assertSame(['user_id' => 'jane', 'name' => 'Jane', 'username' => 'jane', 'preferred_clan' => 'phoenix', 'settings' => ['card_display' => 'images', 'theme' => 'auto'], 'banner' => ['mon' => 'enso', 'pattern' => 'none'], 'unlocked' => ['mons' => ['enso'], 'patterns' => ['none']]], $this->decode($response));
        self::assertSame('phoenix', $this->decode($this->get('/api/self', 'jane'))['preferred_clan']);
    }

    public function testAUserChangesTheirNameAndPreferredClanAtOnce(): void
    {
        $response = $this->request('PATCH', '/api/self', 'jane', ['name' => 'Shiba Tsukune', 'preferred_clan' => 'phoenix']);

        self::assertSame(['user_id' => 'jane', 'name' => 'Shiba Tsukune', 'username' => 'jane', 'preferred_clan' => 'phoenix', 'settings' => ['card_display' => 'images', 'theme' => 'auto'], 'banner' => ['mon' => 'enso', 'pattern' => 'none'], 'unlocked' => ['mons' => ['enso'], 'patterns' => ['none']]], $this->decode($response));
    }

    public function testAUserChangesTheirCardDisplay(): void
    {
        $response = $this->request('PATCH', '/api/self', 'jane', ['settings' => ['card_display' => 'drawn']]);

        self::assertSame(['card_display' => 'drawn', 'theme' => 'auto'], $this->decode($response)['settings']);
        self::assertSame(['card_display' => 'drawn', 'theme' => 'auto'], $this->decode($this->get('/api/self', 'jane'))['settings']);
        self::assertSame('Jane', $this->decode($this->get('/api/self', 'jane'))['name']);
    }

    public function testAUserChangesTheirThemeAndKeepsTheirOtherSettings(): void
    {
        $this->request('PATCH', '/api/self', 'jane', ['settings' => ['card_display' => 'drawn']]);
        $response = $this->request('PATCH', '/api/self', 'jane', ['settings' => ['theme' => 'dark']]);

        self::assertSame(['card_display' => 'drawn', 'theme' => 'dark'], $this->decode($response)['settings']);
    }

    public function testRejectsAnUnknownTheme(): void
    {
        $problem = $this->decodeProblem($this->request('PATCH', '/api/self', 'jane', ['settings' => ['theme' => 'sepia']]), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertIsArray($problem['violations']);
        self::assertSame(['settings.theme'], array_column($problem['violations'], 'property_path'));
    }

    public function testRejectsAnUnknownCardDisplay(): void
    {
        $problem = $this->decodeProblem($this->request('PATCH', '/api/self', 'jane', ['settings' => ['card_display' => 'ascii']]), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame('validation_failed', $problem['type']);
        self::assertIsArray($problem['violations']);
        self::assertSame(['settings.card_display'], array_column($problem['violations'], 'property_path'));
        self::assertSame(['card_display' => 'images', 'theme' => 'auto'], $this->decode($this->get('/api/self', 'jane'))['settings']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidClans(): iterable
    {
        yield 'unknown' => ['mantis'];
        yield 'neutral' => ['neutral'];
    }

    #[DataProvider('invalidClans')]
    public function testRejectsAnInvalidPreferredClan(string $clan): void
    {
        $problem = $this->decodeProblem($this->request('PATCH', '/api/self', 'jane', ['preferred_clan' => $clan]), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame('validation_failed', $problem['type']);
        self::assertIsArray($problem['violations']);
        self::assertSame(['preferred_clan'], array_column($problem['violations'], 'property_path'));
        self::assertSame('crane', $this->decode($this->get('/api/self', 'jane'))['preferred_clan']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidNameBodies(): iterable
    {
        yield 'no name' => [[]];
        yield 'name not a string' => [['name' => 12]];
        yield 'clan not a string' => [['preferred_clan' => null]];
        yield 'unexpected field' => [['name' => 'Doji Hotaru', 'username' => 'hotaru']];
        yield 'settings not an object' => [['settings' => 'images']];
        yield 'settings a list' => [['settings' => ['images']]];
        yield 'setting not a string' => [['settings' => ['card_display' => true]]];
        yield 'unexpected setting' => [['settings' => ['sound' => 'off']]];
        yield 'banner not an object' => [['banner' => 'crab']];
        yield 'banner a list' => [['banner' => ['crab']]];
        yield 'mon not a string' => [['banner' => ['mon' => 3]]];
        yield 'unexpected part of the banner' => [['banner' => ['color' => 'red']]];
    }

    #[DataProvider('invalidNameBodies')]
    public function testRejectsAnInvalidBody(mixed $body): void
    {
        self::assertSame('invalid_payload', $this->decodeProblem($this->request('PATCH', '/api/self', 'jane', $body), Response::HTTP_BAD_REQUEST)['type']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('é', User::MAX_NAME_LENGTH + 1)];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsAnInvalidName(string $name): void
    {
        $problem = $this->decodeProblem($this->request('PATCH', '/api/self', 'jane', ['name' => $name]), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame('validation_failed', $problem['type']);
        self::assertIsArray($problem['violations']);
        self::assertIsArray($problem['violations'][0]);
        self::assertSame('name', $problem['violations'][0]['property_path']);
    }

    public function testAUserFindsTheGamesTheyPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();

        $games = $this->decode($this->get('/api/self/games', 'jane'));

        self::assertSame([[
            'id' => $scenario->game()->getId(),
            'player_id' => $scenario->player('jane')->getId(),
            'version' => $scenario->game()->getVersion(),
            'ended' => false,
            'friendly' => false,
            'players' => ['john', 'jane'],
            // no stronghold in the scenario: no clan
            'seats' => [
                ['user_id' => 'john', 'name' => 'John', 'clan' => null, 'bot' => false],
                ['user_id' => 'jane', 'name' => 'Jane', 'clan' => null, 'bot' => false],
            ],
            'ended_at' => null,
            'winner_id' => null,
            'victory_condition' => null,
        ]], $games);
    }

    public function testTheEndedGamesAreListed(): void
    {
        $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();

        $games = $this->decode($this->get('/api/self/games', 'john'));

        self::assertCount(1, $games);
        self::assertIsArray($games[0]);
        self::assertTrue($games[0]['ended']);
    }

    public function testTheGamesOfOtherUsersAreNotListed(): void
    {
        $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();

        self::assertSame([], $this->decode($this->get('/api/self/games', 'akodo-bot')));
    }

    public function testTheGamesRequireAToken(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->get('/api/self/games', null)->getStatusCode());
    }

    public function testAUserGetsATokenForTheirTopicAndTheirGamesInACookieOfTheHub(): void
    {
        $response = $this->get('/api/self/subscription', 'jane');
        $subscription = $this->decode($response);

        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertIsString($subscription['hub']);
        self::assertStringEndsWith('/.well-known/mercure', $subscription['hub']);
        self::assertSame('/users/jane', $subscription['topic']);
        self::assertArrayNotHasKey('token', $subscription);
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        $cookie = $cookies[0];
        self::assertSame('mercure_access_token', $cookie->getName());
        self::assertSame('/.well-known/mercure', $cookie->getPath());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        self::assertIsString($subscription['expires_at']);
        self::assertSame(new \DateTime($subscription['expires_at'])->getTimestamp(), $cookie->getExpiresTime());

        [$header, $payload] = array_map(
            static fn (string $part): mixed => json_decode(base64_decode(strtr($part, '-_', '+/'), true) ?: '', true, flags: \JSON_THROW_ON_ERROR),
            \array_slice(explode('.', (string) $cookie->getValue()), 0, 2),
        );
        self::assertIsArray($header);
        self::assertSame('at+jwt', $header['typ']);
        self::assertIsArray($payload);
        self::assertSame('https://kyuden.app', $payload['iss']);
        self::assertSame('https://kyuden.app/.well-known/mercure', $payload['aud']);
        self::assertSame('jane', $payload['sub']);
        self::assertSame([[
            'type' => 'https://mercure.rocks/authorization-detail',
            'actions' => ['subscribe'],
            'topics' => [
                ['match' => '/users/jane'],
                ['match' => '/users/jane/games/:gameId', 'match_type' => 'urlpattern'],
            ],
        ]], $payload['authorization_details']);
        self::assertSame($cookie->getExpiresTime(), $payload['exp']);
    }

    public function testTheSubscriptionRequiresAToken(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->get('/api/self/subscription', null)->getStatusCode());
    }

    public function testAHumanDeletesTheirAccount(): void
    {
        $response = $this->request('DELETE', '/api/self', 'jane');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->get('/api/self', 'jane')->getStatusCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNotAnAccount(): iterable
    {
        yield 'a guest' => ['harry'];
        yield 'a bot' => ['akodo-bot'];
    }

    #[DataProvider('provideNotAnAccount')]
    public function testOnlyAnAccountIsDeleted(string $user): void
    {
        self::assertSame('account_required', $this->decodeProblem($this->request('DELETE', '/api/self', $user), Response::HTTP_FORBIDDEN)['type']);
    }

    public function testAUserShowsAMonAndAPatternTheyHaveEarned(): void
    {
        $self = $this->decode($this->request('PATCH', '/api/self', 'john', ['banner' => ['mon' => 'crab', 'pattern' => 'seigaiha']]));

        self::assertSame(['mon' => 'crab', 'pattern' => 'seigaiha'], $self['banner']);
        self::assertSame(['mons' => ['enso', 'crab', 'phoenix'], 'patterns' => ['none', 'plain', 'asanoha', 'seigaiha']], $self['unlocked']);
    }

    public function testAUserChangesTheMonOfTheirNoboriAndKeepsItsPattern(): void
    {
        $this->request('PATCH', '/api/self', 'john', ['banner' => ['pattern' => 'asanoha']]);
        $self = $this->decode($this->request('PATCH', '/api/self', 'john', ['banner' => ['mon' => 'phoenix']]));

        self::assertSame(['mon' => 'phoenix', 'pattern' => 'asanoha'], $self['banner']);
    }

    public function testAUserGoesBackToTheEnsoWithoutPattern(): void
    {
        $this->request('PATCH', '/api/self', 'john', ['banner' => ['mon' => 'crab', 'pattern' => 'plain']]);
        $self = $this->decode($this->request('PATCH', '/api/self', 'john', ['banner' => ['mon' => 'enso', 'pattern' => 'none']]));

        self::assertSame(['mon' => 'enso', 'pattern' => 'none'], $self['banner']);
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function provideBannersNotEarned(): iterable
    {
        yield 'a mon not earned' => [['mon' => 'crane'], 'banner.mon', 'You have not earned this mon yet.'];
        yield 'a pattern not earned' => [['pattern' => 'yagasuri'], 'banner.pattern', 'You have not earned this pattern yet.'];
        yield 'an unknown mon' => [['mon' => 'mantis'], 'banner.mon', 'Choose one of: "enso", "crab", "crane", "dragon", "lion", "phoenix", "scorpion", "unicorn".'];
        yield 'an unknown pattern' => [['pattern' => 'paisley'], 'banner.pattern', 'Choose one of: "none", "plain", "tatejima", "dandara", "ichimatsu", "koshi", "samekomon", "uroko", "nami", "kikko", "asanoha", "seigaiha", "yagasuri".'];
    }

    /**
     * @param array<string, string> $banner
     */
    #[DataProvider('provideBannersNotEarned')]
    public function testRejectsWhatTheUserHasNotEarned(array $banner, string $path, string $message): void
    {
        $problem = $this->decodeProblem($this->request('PATCH', '/api/self', 'john', ['banner' => $banner]), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame('validation_failed', $problem['type']);
        self::assertSame([['property_path' => $path, 'message' => $message]], $problem['violations']);
        self::assertSame(['mon' => 'enso', 'pattern' => 'none'], $this->decode($this->get('/api/self', 'john'))['banner']);
    }

    public function testAGuestHasOnlyTheEnsoWithoutPatternToChooseFrom(): void
    {
        self::assertSame(['mons' => ['enso'], 'patterns' => ['none']], $this->decode($this->get('/api/self', 'harry'))['unlocked']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->request('PATCH', '/api/self', 'harry', ['banner' => ['mon' => 'crab']])->getStatusCode());
    }
}
