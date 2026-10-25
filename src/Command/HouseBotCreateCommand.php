<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The house bot (HOUSE_BOT_ID) and the platform that owns it, created when missing: it runs on every start of the
 * production containers. The bot gets no API key: it plays in the requests of its opponents.
 */
#[AsCommand(
    name: 'app:house-bot:create',
    description: 'Create the house bot (HOUSE_BOT_ID) and its owner, the platform, when they are missing',
)]
readonly class HouseBotCreateCommand
{
    public const string OWNER_ID = 'kyuden';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $houseBotId,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->houseBotId === '') {
            throw new \RuntimeException('HOUSE_BOT_ID is empty.');
        }
        $bot =$this->entityManager->find(User::class, $this->houseBotId);
        if ($bot instanceof User) {
            if ($bot->getType() !== UserType::Bot) {
                throw new \RuntimeException("User {$this->houseBotId} is not a bot.");
            }
            $io->success("House bot {$this->houseBotId} already exists.");

            return Command::SUCCESS;
        }

        $owner = $this->entityManager->find(User::class, self::OWNER_ID);
        if (! $owner instanceof User) {
            $owner = new User(self::OWNER_ID, 'Kyūden', UserType::Human);
            $this->entityManager->persist($owner);
        } elseif ($owner->getType() !== UserType::Human) {
            throw new \RuntimeException('User ' . self::OWNER_ID . ' is not a human: it cannot own the house bot.');
        }
        $this->entityManager->persist(new User($this->houseBotId, 'Kyūden Bot', UserType::Bot, $owner));
        $this->entityManager->flush();
        $io->success("House bot {$this->houseBotId} created.");

        return Command::SUCCESS;
    }
}
