<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Security\AccessTokenHandler;
use App\Tests\AbstractKernelTestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class AccessTokenHandlerTest extends AbstractKernelTestCase
{
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
}
