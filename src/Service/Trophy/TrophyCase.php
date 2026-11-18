<?php

declare(strict_types=1);

namespace App\Service\Trophy;

use App\Entity\EarnedTrophy;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\Trophy;
use App\Repository\EarnedTrophyRepository;
use App\Service\Nobori\NoboriCollection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The trophies a user has earned (EarnedTrophy), each once: earning one gives its reward for the nobori.
 */
readonly class TrophyCase
{
    public function __construct(
        private EarnedTrophyRepository $earnedTrophyRepository,
        private NoboriCollection $noboriCollection,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function has(User $user, Trophy $trophy): bool
    {
        return $this->earnedTrophyRepository->find(['user' => $user, 'trophy' => $trophy]) instanceof EarnedTrophy;
    }

    /**
     * @return array<string, EarnedTrophy> what the user has earned, by the value of the trophy
     */
    public function earned(User $user): array
    {
        $earned = [];
        foreach ($this->earnedTrophyRepository->findByUser($user) as $earnedTrophy) {
            $earned[$earnedTrophy->getTrophy()->value] = $earnedTrophy;
        }

        return $earned;
    }

    /**
     * The user earns a trophy, in a game or not, and its reward (persisted, not flushed). False when they had it
     * already: a trophy earned stays earned, with the date and the game it was first earned in.
     */
    public function award(User $user, Trophy $trophy, \DateTimeImmutable $earnedAt, ?Game $game = null): bool
    {
        if ($this->has($user, $trophy)) {
            return false;
        }

        $this->entityManager->persist(new EarnedTrophy($user, $trophy, $earnedAt, $game));
        $this->noboriCollection->unlock($user, $trophy->getReward());

        return true;
    }

    /**
     * Forgets what the user has earned (the account is deleted).
     */
    public function forget(User $user): void
    {
        $this->earnedTrophyRepository->deleteByUser($user);
    }
}
