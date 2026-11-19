<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ActionRecord;
use App\Repository\ActionRecordRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:record:list',
    description: 'List the last N records',
)]
readonly class RecordListCommand
{
    public function __construct(
        private ActionRecordRepository $repository,
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
        $records = $this->repository->findBy([], [
            'id' => 'DESC',
        ], $limit);
        $io->table(
            ['Id', 'Name', 'Payload'],
            array_map(
                fn (ActionRecord $record): array => [$record->getId(), $record->getName(), $record->getPayload()],
                $records
            )
        );

        return Command::SUCCESS;
    }
}
