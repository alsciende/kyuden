<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Enum\UserType;
use App\Security\AccessTokenHandler;
use App\Security\AccessTokenIssuer;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class UserAndTokenCommandsTest extends AbstractKernelTestCase
{
    private function tester(string $name): CommandTester
    {
        return new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find($name));
    }

    public function testABotGetsAnAPIKeyThatAuthenticatesItUntilRevoked(): void
    {
        $this->tester('app:user:create')->execute(['id' => 'shiba-bot', 'name' => 'Shiba Bot', '--bot' => true, '--owner' => 'john']);
        $user = self::service(EntityManagerInterface::class)->find(User::class, 'shiba-bot');
        self::assertInstanceOf(User::class, $user);
        self::assertSame(UserType::Bot, $user->getType());
        self::assertSame('john', $user->getOwner()?->getId());

        $tester = $this->tester('app:token:create');
        $tester->execute(['user-id' => 'shiba-bot', '--api-key' => true]);
        $lines = explode("\n", trim($tester->getDisplay()));
        $token = end($lines);
        self::assertStringStartsWith(AccessTokenIssuer::PREFIX, $token);
        $handler = self::service(AccessTokenHandler::class);
        self::assertSame('shiba-bot', $handler->getUserBadgeFrom($token)->getUserIdentifier());

        $this->tester('app:token:revoke')->execute(['user-id' => 'shiba-bot']);
        $this->expectException(BadCredentialsException::class);
        $handler->getUserBadgeFrom($token);
    }

    public function testAHumanGetsNoAPIKey(): void
    {
        $this->tester('app:user:create')->execute(['id' => 'bob', 'name' => 'Bob']);

        $this->expectExceptionMessage('Only a bot gets API keys.');
        $this->tester('app:token:create')->execute(['user-id' => 'bob', '--api-key' => true]);
    }

    public function testAUserIdIsTakenOnce(): void
    {
        $this->expectExceptionMessage('User john already exists.');
        $this->tester('app:user:create')->execute(['id' => 'john', 'name' => 'John']);
    }
}
