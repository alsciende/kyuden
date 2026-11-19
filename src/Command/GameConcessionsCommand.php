<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ActionRecord;
use App\Enum\UserType;
use App\Message\ConcedeGame;
use App\Repository\ActionRecordRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:game:concessions',
    description: 'List the last concessions (not in the replays), the latest first, with the reason the player gave',
)]
readonly class GameConcessionsCommand
{
    public function __construct(
        private ActionRecordRepository $recordRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('How many concessions to list')]
        int $limit = 20,
    ): int {
        if ($limit < 1) {
            throw new \RuntimeException('Option --limit must be a positive number.');
        }

        $records = $this->recordRepository->findLatestOf(ConcedeGame::class, $limit);
        if ($records === []) {
            $io->writeln('No concession.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Date', 'Game', 'Version', 'Player', 'Reason'],
            array_map($this->describe(...), $records),
        );

        return Command::SUCCESS;
    }

    /**
     * @return list<string|int>
     */
    private function describe(ActionRecord $record): array
    {
        /** @var array{player_id: string, reason?: string|null} $payload */
        $payload = json_decode($record->getPayload(), true, flags: JSON_THROW_ON_ERROR);
        $user = $record->getGame()->getPlayer($payload['player_id'])->getUser();

        return [
            $record->getCreatedAt()?->format('Y-m-d H:i') ?? '',
            $record->getGame()->getId(),
            $record->getVersion(),
            sprintf('%s [%s]%s', $user->getName(), $user->getId(), $user->getType() === UserType::Bot ? ' (bot)' : ''),
            $payload['reason'] ?? '—',
        ];
    }
}
