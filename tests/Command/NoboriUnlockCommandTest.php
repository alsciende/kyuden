<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Enum\BannerPattern;
use App\Enum\Mon;
use App\Service\Nobori\NoboriCollection;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class NoboriUnlockCommandTest extends AbstractKernelTestCase
{
    /**
     * @param list<string> $items
     */
    private function unlock(string $user, array $items): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:nobori:unlock'));
        $tester->execute(['user' => $user, 'items' => $items]);

        return $tester;
    }

    public function testAUserIsGivenMonsAndPatternsByUsername(): void
    {
        $tester = $this->unlock('jane', ['dragon', 'kikko', 'dragon']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('jane has dragon already.', $tester->getDisplay());
        $jane = self::service(EntityManagerInterface::class)->find(User::class, 'jane');
        self::assertInstanceOf(User::class, $jane);
        self::assertSame([Mon::Enso, Mon::Dragon], self::service(NoboriCollection::class)->mons($jane));
        self::assertSame([BannerPattern::None, BannerPattern::Kikko], self::service(NoboriCollection::class)->patterns($jane));
    }

    public function testNothingIsGivenWhenAValueIsUnknown(): void
    {
        $tester = $this->unlock('jane', ['dragon', 'paisley']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Neither a mon nor a pattern: paisley.', $tester->getDisplay());
        $jane = self::service(EntityManagerInterface::class)->find(User::class, 'jane');
        self::assertInstanceOf(User::class, $jane);
        self::assertSame([Mon::Enso], self::service(NoboriCollection::class)->mons($jane));
    }
}
