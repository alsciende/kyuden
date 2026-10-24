<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ApiGuestsController;
use App\Entity\User;
use App\Enum\UserType;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class ApiGuestsControllerTest extends AbstractKernelTestCase
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
    private function decode(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    public function testAnyoneGetsAGuestAccountWithASessionToken(): void
    {
        $response = $this->request('POST', '/api/guests', ['name' => '  Hida Takeshi ']);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $guest = $this->decode($response);
        self::assertIsString($guest['user_id']);
        self::assertTrue(Uuid::isValid($guest['user_id']));
        self::assertSame('Hida Takeshi', $guest['name']);
        self::assertIsString($guest['token']);

        $user = self::service(EntityManagerInterface::class)->find(User::class, $guest['user_id']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame(UserType::Human, $user->getType());
        self::assertTrue($user->isGuest());

        $self = $this->request('GET', '/api/self', token: $guest['token']);
        self::assertSame(['user_id' => $guest['user_id'], 'name' => 'Hida Takeshi', 'username' => null, 'preferred_clan' => null, 'settings' => ['card_display' => 'drawn']], $this->decode($self));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'no name' => [[]];
        yield 'blank name' => [['name' => '   ']];
        yield 'name too long' => [['name' => str_repeat('a', ApiGuestsController::MAX_NAME_LENGTH + 1)]];
        yield 'name not a string' => [['name' => 12]];
        yield 'unexpected field' => [['name' => 'Doji Aiko', 'id' => 'admin']];
    }

    #[DataProvider('invalidBodies')]
    public function testRejectsAnInvalidName(mixed $body): void
    {
        $response = $this->request('POST', '/api/guests', $body);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('invalid_payload', $this->decode($response)['type']);
    }
}
