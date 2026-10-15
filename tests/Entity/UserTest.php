<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AccessToken;
use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Enum\UserType;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testABotHasAHumanOwner(): void
    {
        $john = new User('john', 'John', UserType::Human);

        self::assertSame($john, new User('bot', 'Bot', UserType::Bot, $john)->getOwner());
    }

    public function testABotWithoutOwnerIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User('bot', 'Bot', UserType::Bot);
    }

    public function testABotOwnedByABotIsRejected(): void
    {
        $bot = new User('bot', 'Bot', UserType::Bot, new User('john', 'John', UserType::Human));

        $this->expectException(\InvalidArgumentException::class);

        new User('bot2', 'Bot 2', UserType::Bot, $bot);
    }

    public function testAHumanGetsNoAPIKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AccessToken(new User('john', 'John', UserType::Human), 'secret', AccessTokenType::ApiKey);
    }

    public function testOnlyTheHashOfTheTokenIsStored(): void
    {
        $token = new AccessToken(new User('john', 'John', UserType::Human), 'secret', AccessTokenType::Session);

        self::assertSame(hash('sha256', 'secret'), $token->getHash());
    }
}
