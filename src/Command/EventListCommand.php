<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:event:list',
    description: 'List the last N events',
)]
readonly class EventListCommand
{
    public function __construct(
        private EventRepository $repository,
    ) {
    }

    public function __invoke(
        #[Option]
        string $lines,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        if (! is_numeric($lines)) {
            throw new \RuntimeException('Option --lines must be numeric.');
        }
        $limit = intval($lines);
        $events = $this->repository->findBy([], [
            'id' => 'desc',
        ], $limit);
        $io->table(
            ['Id', 'Name', 'Payload'],
            array_map(
                fn (Event $event): array => [$event->getId(), $event->getName(), $event->getPayload()],
                $events
            )
        );

        return Command::SUCCESS;
    }
}
