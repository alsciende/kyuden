<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\CardSource;
use App\Exception\Api\InvalidDeckException;
use App\Repository\UserRepository;
use App\Service\Deck\DeckImporter;
use App\Service\Deck\EmeraldDbDeckFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:deck:import',
    description: 'Import a deck of EmeraldDB from its URL for a user, checked against the deckbuilding rules',
)]
readonly class DeckImportCommand
{
    public function __construct(
        private EmeraldDbDeckFetcher $fetcher,
        private DeckImporter $deckImporter,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(
        #[Argument('URL of the deck on EmeraldDB (https://www.emeralddb.org/decks/<id>)')]
        string $url,
        InputInterface $input,
        OutputInterface $output,
        #[Option('User ID or username of the owner of the deck')]
        ?string $user = null,
        #[Option('Name of the deck, instead of its name on EmeraldDB')]
        ?string $name = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        if ($user === null) {
            throw new \RuntimeException('The owner of the deck is required (--user).');
        }
        $owner = $this->entityManager->find(User::class, $user) ?? $this->userRepository->findOneByUsername($user);
        if (! $owner instanceof User) {
            throw new \RuntimeException("User {$user} not found.");
        }

        $fetched = $this->fetcher->fetch($url);
        try {
            $deck = $this->deckImporter->import($owner, $name ?? $fetched['name'], CardSource::EmeraldDb->value, $fetched['cards']);
        } catch (InvalidDeckException $exception) {
            $io->error('The deck cannot be imported:');
            $io->listing(array_map(
                fn (array $violation): string => "{$violation['property_path']}: {$violation['message']}",
                $exception->getViolations(),
            ));

            return Command::FAILURE;
        }
        $this->entityManager->flush();

        $io->success(sprintf('Deck %s imported for %s: %s (%d cards).', $deck->getId(), $owner->getId(), $deck->getName() ?? 'no name', array_sum($deck->getCards())));

        return Command::SUCCESS;
    }
}
