<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\DataFixtures\UserFixtures;
use App\Entity\AccessToken;
use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Repository\AccessTokenRepository;
use App\Security\AccessTokenHandler;
use App\Security\AccessTokenIssuer;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class AccessTokenHandlerTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    private const int DAY = 86400;

    /**
     * A new token of the user, issued now.
     */
    private function issue(string $userId, AccessTokenType $type): string
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $user = $entityManager->find(User::class, $userId) ?? self::fail("No user {$userId}.");
        $token = self::service(AccessTokenIssuer::class)->issue($user, $type);
        $entityManager->flush();

        return $token;
    }

    private function authenticate(string $token): string
    {
        return self::service(AccessTokenHandler::class)->getUserBadgeFrom($token)->getUserIdentifier();
    }

    private function lastUsedAt(string $token): string
    {
        self::service(EntityManagerInterface::class)->clear();
        $accessToken = self::service(AccessTokenRepository::class)->findOneByToken($token);
        self::assertInstanceOf(AccessToken::class, $accessToken);

        return $accessToken->getLastUsedAt()->format('Y-m-d H:i:s');
    }

    public function testAuthenticatesTheUserOfAToken(): void
    {
        $badge = self::service(AccessTokenHandler::class)->getUserBadgeFrom(UserFixtures::TOKENS['akodo-bot']);

        self::assertSame('akodo-bot', $badge->getUserIdentifier());
        $user = $badge->getUser();
        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_BOT', $user->getRoles());
    }

    public function testAUserIdentifierIsNotAToken(): void
    {
        $this->expectException(BadCredentialsException::class);

        self::service(AccessTokenHandler::class)->getUserBadgeFrom('john');
    }

    public function testASessionOfAnAccountExpiresOnceUnusedFor30Days(): void
    {
        $clock = self::mockTime('2026-11-01 12:00:00');
        $token = $this->issue('john', AccessTokenType::Session);

        $clock->sleep(30 * self::DAY + 1);

        $this->expectException(BadCredentialsException::class);
        $this->authenticate($token);
    }

    public function testEachUseExtendsTheSession(): void
    {
        $clock = self::mockTime('2026-11-01 12:00:00');
        $token = $this->issue('john', AccessTokenType::Session);

        $clock->sleep(29 * self::DAY);
        self::assertSame('john', $this->authenticate($token));
        self::assertSame('2026-11-30 12:00:00', $this->lastUsedAt($token));

        $clock->sleep(29 * self::DAY);
        self::assertSame('john', $this->authenticate($token));
    }

    public function testTheUseIsRecordedAtMostOnceAnHour(): void
    {
        $clock = self::mockTime('2026-11-01 12:00:00');
        $token = $this->issue('john', AccessTokenType::Session);

        $clock->sleep(59 * 60);
        $this->authenticate($token);
        self::assertSame('2026-11-01 12:00:00', $this->lastUsedAt($token));

        $clock->sleep(2 * 60);
        $this->authenticate($token);
        self::assertSame('2026-11-01 13:01:00', $this->lastUsedAt($token));
    }

    public function testTheSessionOfAGuestNeverExpires(): void
    {
        $clock = self::mockTime('2026-11-01 12:00:00');
        $token = $this->issue('harry', AccessTokenType::Session);

        $clock->sleep(365 * self::DAY);

        self::assertSame('harry', $this->authenticate($token));
    }

    public function testAnApiKeyNeverExpires(): void
    {
        $clock = self::mockTime('2026-11-01 12:00:00');
        $token = $this->issue('akodo-bot', AccessTokenType::ApiKey);

        $clock->sleep(365 * self::DAY);

        self::assertSame('akodo-bot', $this->authenticate($token));
        self::assertSame('2026-11-01 12:00:00', $this->lastUsedAt($token));
    }
}
