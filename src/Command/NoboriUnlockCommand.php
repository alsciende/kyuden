<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\BannerPattern;
use App\Enum\Mon;
use App\Repository\UserRepository;
use App\Service\Nobori\NoboriCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gives a user mons and patterns for their nobori, by hand, until the players earn them by playing.
 */
#[AsCommand(
    name: 'app:nobori:unlock',
    description: 'Give a user mons or patterns for their nobori (by their value: crab, seigaiha…)',
)]
readonly class NoboriUnlockCommand
{
    public function __construct(
        private NoboriCollection $noboriCollection,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @param list<string> $items
     */
    public function __invoke(
        #[Argument('User ID or username')]
        string $user,
        #[Argument('The mons and the patterns to give, by their value')]
        array $items,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $owner = $this->entityManager->find(User::class, $user) ?? $this->userRepository->findOneByUsername($user);
        if (! $owner instanceof User) {
            throw new \RuntimeException("User {$user} not found.");
        }

        $unknown = array_filter($items, static fn (string $item): bool => ! Mon::tryFrom($item) instanceof Mon && ! BannerPattern::tryFrom($item) instanceof BannerPattern);
        if ($unknown !== []) {
            $io->error(sprintf('Neither a mon nor a pattern: %s. Mons: %s. Patterns: %s.', implode(', ', $unknown), implode(', ', array_column(Mon::cases(), 'value')), implode(', ', array_column(BannerPattern::cases(), 'value'))));

            return Command::FAILURE;
        }

        foreach ($items as $item) {
            $value = Mon::tryFrom($item) ?? BannerPattern::from($item);
            $this->noboriCollection->unlock($owner, $value)
                ? $io->writeln("{$owner->getId()} earns {$item}.")
                : $io->writeln("{$owner->getId()} has {$item} already.");
        }
        $this->entityManager->flush();
        $io->success(sprintf(
            '%s may choose the mons %s and the patterns %s.',
            $owner->getId(),
            implode(', ', array_column($this->noboriCollection->mons($owner), 'value')),
            implode(', ', array_column($this->noboriCollection->patterns($owner), 'value')),
        ));

        return Command::SUCCESS;
    }
}
