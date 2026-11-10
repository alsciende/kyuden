<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\AccessToken;
use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Repository\AccessTokenRepository;
use App\Security\AccessTokenIssuer;
use App\Security\ExpiredSessionsDeleter;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class ExpiredSessionsDeleterTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    private function issue(string $userId, AccessTokenType $type): string
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $user = $entityManager->find(User::class, $userId) ?? self::fail("No user {$userId}.");
        $token = self::service(AccessTokenIssuer::class)->issue($user, $type);
        $entityManager->flush();

        return $token;
    }

    private function exists(string $token): bool
    {
        self::service(EntityManagerInterface::class)->clear();

        return self::service(AccessTokenRepository::class)->findOneByToken($token) instanceof AccessToken;
    }

    public function testDeletesTheExpiredSessionsOfTheAccountsOnly(): void
    {
        $clock = self::mockTime('2026-11-01 12:00:00');
        $expired = $this->issue('john', AccessTokenType::Session);
        $guest = $this->issue('harry', AccessTokenType::Session);
        $apiKey = $this->issue('akodo-bot', AccessTokenType::ApiKey);
        $clock->sleep(86400);
        $recent = $this->issue('jane', AccessTokenType::Session);

        $clock->sleep(30 * 86400 - 3600);
        self::assertSame(1, self::service(ExpiredSessionsDeleter::class)->deleteAll());

        self::assertFalse($this->exists($expired));
        self::assertTrue($this->exists($recent));
        self::assertTrue($this->exists($guest));
        self::assertTrue($this->exists($apiKey));
    }
}
