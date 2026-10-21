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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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

    public function testAHumanWithAUsernameSignsInWithThePasswordAsked(): void
    {
        $tester = $this->tester('app:user:create');
        $tester->setInputs(['short', 'a long enough password']);
        $tester->execute(['id' => 'bob', 'name' => 'Bob', '--username' => 'Bob_42']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('This value is too short.', $tester->getDisplay());
        $user = self::service(EntityManagerInterface::class)->find(User::class, 'bob');
        self::assertInstanceOf(User::class, $user);
        self::assertSame('Bob_42', $user->getUsername());
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, 'a long enough password'));
    }

    public function testAUsernameIsTakenOnceWhateverItsCase(): void
    {
        $this->expectExceptionMessage('The username JOHN is taken.');
        $this->tester('app:user:create')->execute(['id' => 'bob', 'name' => 'Bob', '--username' => 'JOHN'], ['interactive' => false]);
    }

    public function testAUsernameHasOnlyLettersDigitsAndUnderscores(): void
    {
        $this->expectExceptionMessage('The username has 3 to 32 letters, digits or _.');
        $this->tester('app:user:create')->execute(['id' => 'bob', 'name' => 'Bob', '--username' => 'bob.smith'], ['interactive' => false]);
    }
}
