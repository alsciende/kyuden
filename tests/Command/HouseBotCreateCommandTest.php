<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\HouseBotCreateCommand;
use App\Entity\User;
use App\Enum\UserType;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class HouseBotCreateCommandTest extends AbstractKernelTestCase
{
    private function createHouseBot(string $houseBotId): string
    {
        $output = new BufferedOutput();
        $command = new HouseBotCreateCommand(self::service(EntityManagerInterface::class), $houseBotId);
        self::assertSame(0, $command(new ArrayInput([]), $output));

        return $output->fetch();
    }

    public function testTheHouseBotIsCreatedOwnedByThePlatform(): void
    {
        self::assertStringContainsString('House bot shiba-bot created.', $this->createHouseBot('shiba-bot'));

        $bot = self::service(EntityManagerInterface::class)->find(User::class, 'shiba-bot');
        self::assertInstanceOf(User::class, $bot);
        self::assertSame(UserType::Bot, $bot->getType());
        self::assertSame(HouseBotCreateCommand::OWNER_ID, $bot->getOwner()?->getId());
    }

    public function testAHouseBotThatExistsIsKept(): void
    {
        self::assertStringContainsString('House bot kyuden-bot already exists.', $this->createHouseBot('kyuden-bot'));
    }

    public function testTheHouseBotIsABot(): void
    {
        $this->expectExceptionMessage('User john is not a bot.');
        $this->createHouseBot('john');
    }
}
