<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\Deck;
use App\Entity\User;
use App\Enum\Clan;
use App\Enum\UserType;
use App\Repository\DeckRepository;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class ApiAccountsControllerTest extends AbstractKernelTestCase
{
    private function request(string $method, string $uri, mixed $body = null, ?string $token = null): Response
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

    private function user(string $id): User
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->find(User::class, $id);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    public function testAGuestKeepsTheirUserAndNameAsAnAccount(): void
    {
        $account = $this->decode($this->request('POST', '/api/accounts', ['username' => 'harry_h', 'password' => 'mot de passe', 'clan' => 'scorpion'], UserFixtures::TOKENS['harry']), Response::HTTP_CREATED);

        self::assertSame('harry', $account['user_id']);
        self::assertSame('Harry', $account['name']);
        self::assertSame('harry_h', $account['username']);
        self::assertSame('scorpion', $account['preferred_clan']);
        self::assertIsString($account['token']);
        self::assertNotSame(UserFixtures::TOKENS['harry'], $account['token']);

        $user = $this->user('harry');
        self::assertFalse($user->isGuest());
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, 'mot de passe'));

        // both tokens are the same user's
        $self = $this->decode($this->request('GET', '/api/self', token: $account['token']), Response::HTTP_OK);
        self::assertSame(['user_id' => 'harry', 'name' => 'Harry', 'username' => 'harry_h', 'preferred_clan' => 'scorpion', 'settings' => ['card_display' => 'drawn', 'theme' => 'auto']], $self);
        self::assertSame(Response::HTTP_OK, $this->request('GET', '/api/self', token: UserFixtures::TOKENS['harry'])->getStatusCode());
    }

    public function testAnyoneCreatesAnAccountWithANameAtCourt(): void
    {
        $account = $this->decode($this->request('POST', '/api/accounts', ['username' => 'Kakita_Ryoku', 'password' => '鶴の一族の秘密だ', 'clan' => 'crane', 'name' => ' Kakita Ryoku ']), Response::HTTP_CREATED);

        self::assertIsString($account['user_id']);
        self::assertTrue(Uuid::isValid($account['user_id']));
        self::assertSame('Kakita Ryoku', $account['name']);
        self::assertSame('Kakita_Ryoku', $account['username']);
        $user = $this->user($account['user_id']);
        self::assertSame(UserType::Human, $user->getType());
        self::assertSame('Kakita_Ryoku', $user->getUsername());
        self::assertSame(Clan::Crane, $user->getPreferredClan());
    }

    public function testTheAccountGetsACopyOfTheStarterDeckOfItsClan(): void
    {
        $account = $this->decode($this->request('POST', '/api/accounts', ['username' => 'harry_h', 'password' => 'mot de passe', 'clan' => 'scorpion'], UserFixtures::TOKENS['harry']), Response::HTTP_CREATED);

        $decks = self::service(DeckRepository::class)->findBy(['owner' => $account['user_id']]);
        self::assertCount(1, $decks);
        $starterDeck = self::service(DeckRepository::class)->find(Clan::Scorpion->getStarterDeckId());
        self::assertInstanceOf(Deck::class, $starterDeck);
        self::assertNotSame($starterDeck->getId(), $decks[0]->getId());
        self::assertSame('Scorpion starter deck', $decks[0]->getName());
        self::assertSame($starterDeck->getCards(), $decks[0]->getCards());
    }

    public function testNoDeckIsCopiedWhenTheAccountIsRejected(): void
    {
        $this->decode($this->request('POST', '/api/accounts', ['username' => 'JANE', 'password' => 'password', 'clan' => 'crab'], UserFixtures::TOKENS['harry']), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame([], self::service(DeckRepository::class)->findBy(['owner' => 'harry']));
    }

    public function testAUserWithAnAccountCannotCreateAnother(): void
    {
        $problem = $this->decode($this->request('POST', '/api/accounts', ['username' => 'john_2', 'password' => 'password', 'clan' => 'crab'], UserFixtures::TOKENS['john']), Response::HTTP_CONFLICT);

        self::assertSame('already_registered', $problem['type']);
    }

    public function testABotCannotCreateAnAccount(): void
    {
        $problem = $this->decode($this->request('POST', '/api/accounts', ['username' => 'akodo', 'password' => 'password', 'clan' => 'lion'], UserFixtures::TOKENS['akodo-bot']), Response::HTTP_CONFLICT);

        self::assertSame('already_registered', $problem['type']);
    }

    public function testAnUnknownTokenIsRejected(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('POST', '/api/accounts', ['username' => 'nobody', 'password' => 'password', 'clan' => 'crab', 'name' => 'Nobody'], 'l5r_unknown')->getStatusCode());
    }

    public function testAUsernameIsTakenWhateverItsCase(): void
    {
        $problem = $this->decode($this->request('POST', '/api/accounts', ['username' => 'JANE', 'password' => 'password', 'clan' => 'crab'], UserFixtures::TOKENS['harry']), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame('validation_failed', $problem['type']);
        self::assertSame([['property_path' => 'username', 'message' => 'This username is taken.']], $problem['violations']);
        self::assertTrue($this->user('harry')->isGuest());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'username too short' => [['username' => 'ab', 'password' => 'password', 'clan' => 'crab', 'name' => 'Name'], 'username'];
        yield 'username too long' => [['username' => str_repeat('a', 33), 'password' => 'password', 'clan' => 'crab', 'name' => 'Name'], 'username'];
        yield 'username with a dash' => [['username' => 'hida-takeshi', 'password' => 'password', 'clan' => 'crab', 'name' => 'Name'], 'username'];
        yield 'username with a space' => [['username' => 'Hida Takeshi', 'password' => 'password', 'clan' => 'crab', 'name' => 'Name'], 'username'];
        yield 'username with an accent' => [['username' => 'Kyūden', 'password' => 'password', 'clan' => 'crab', 'name' => 'Name'], 'username'];
        yield 'username ending with a newline' => [['username' => "hida\n", 'password' => 'password', 'clan' => 'crab', 'name' => 'Name'], 'username'];
        // 7 characters, 21 bytes
        yield 'password too short' => [['username' => 'hida', 'password' => '蟹蟹蟹蟹蟹蟹蟹', 'clan' => 'crab', 'name' => 'Name'], 'password'];
        yield 'password too long' => [['username' => 'hida', 'password' => str_repeat('a', 4097), 'clan' => 'crab', 'name' => 'Name'], 'password'];
        yield 'blank name' => [['username' => 'hida', 'password' => 'password', 'clan' => 'crab', 'name' => '  '], 'name'];
        yield 'unknown clan' => [['username' => 'hida', 'password' => 'password', 'clan' => 'mantis', 'name' => 'Name'], 'clan'];
        yield 'neutral, not a clan one prefers' => [['username' => 'hida', 'password' => 'password', 'clan' => 'neutral', 'name' => 'Name'], 'clan'];
        yield 'name too long' => [['username' => 'hida', 'password' => 'password', 'clan' => 'crab', 'name' => str_repeat('a', User::MAX_NAME_LENGTH + 1)], 'name'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(array $body, string $property): void
    {
        $problem = $this->decode($this->request('POST', '/api/accounts', $body), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame('validation_failed', $problem['type']);
        self::assertIsArray($problem['violations']);
        self::assertSame([$property], array_unique(array_column($problem['violations'], 'property_path')));
    }

    public function testAPasswordOfEightCharactersIsLongEnough(): void
    {
        $this->decode($this->request('POST', '/api/accounts', ['username' => 'hida', 'password' => '蟹蟹蟹蟹蟹蟹蟹蟹', 'clan' => 'crab', 'name' => 'Hida']), Response::HTTP_CREATED);
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'no username' => [['password' => 'password', 'clan' => 'crab', 'name' => 'Name'], null];
        yield 'no clan' => [['username' => 'hida', 'password' => 'password', 'name' => 'Name'], null];
        yield 'no clan for a guest' => [['username' => 'hida', 'password' => 'password'], 'harry'];
        yield 'password not a string' => [['username' => 'hida', 'password' => 12345678, 'clan' => 'crab', 'name' => 'Name'], null];
        yield 'no name for a new user' => [['username' => 'hida', 'password' => 'password', 'clan' => 'crab'], null];
        yield 'unexpected field' => [['username' => 'hida', 'password' => 'password', 'clan' => 'crab', 'name' => 'Name', 'id' => 'admin'], null];
        yield 'a name for a guest, who keeps theirs' => [['username' => 'hida', 'password' => 'password', 'clan' => 'crab', 'name' => 'Name'], 'harry'];
    }

    #[DataProvider('invalidBodies')]
    public function testRejectsAnInvalidBody(mixed $body, ?string $user): void
    {
        $response = $this->request('POST', '/api/accounts', $body, $user === null ? null : UserFixtures::TOKENS[$user]);

        self::assertSame('invalid_payload', $this->decode($response, Response::HTTP_BAD_REQUEST)['type']);
    }
}
