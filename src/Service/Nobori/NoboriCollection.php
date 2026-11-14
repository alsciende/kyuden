<?php

declare(strict_types=1);

namespace App\Service\Nobori;

use App\Entity\UnlockedMon;
use App\Entity\UnlockedPattern;
use App\Entity\User;
use App\Enum\BannerPattern;
use App\Enum\Clan;
use App\Enum\Mon;
use App\Model\Banner;
use App\Repository\UnlockedMonRepository;
use App\Repository\UnlockedPatternRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * What a user may show on their nobori: the ensō and no pattern, which every player has from the start, and the mons
 * and the patterns they have earned (UnlockedMon, UnlockedPattern). How they earn them is not decided yet: whatever
 * gives one calls unlock(). The house bot shows the mon of the clan it plays, on a plain nobori.
 */
readonly class NoboriCollection
{
    public function __construct(
        private UnlockedMonRepository $unlockedMonRepository,
        private UnlockedPatternRepository $unlockedPatternRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private string $houseBotId,
    ) {
    }

    /**
     * @return list<Mon> the mons the user may choose, in the order of the enum
     */
    public function mons(User $user): array
    {
        $unlocked = $this->unlockedMonRepository->findValuesByUser($user);

        return array_values(array_filter(Mon::cases(), static fn (Mon $mon): bool => $mon->isGivenToAll() || in_array($mon, $unlocked, true)));
    }

    /**
     * @return list<BannerPattern> the patterns the user may choose, in the order of the enum
     */
    public function patterns(User $user): array
    {
        $unlocked = $this->unlockedPatternRepository->findValuesByUser($user);

        return array_values(array_filter(BannerPattern::cases(), static fn (BannerPattern $pattern): bool => $pattern->isGivenToAll() || in_array($pattern, $unlocked, true)));
    }

    public function has(User $user, Mon|BannerPattern $item): bool
    {
        if ($item->isGivenToAll()) {
            return true;
        }

        return $item instanceof Mon
            ? $this->unlockedMonRepository->find(['user' => $user, 'mon' => $item]) instanceof UnlockedMon
            : $this->unlockedPatternRepository->find(['user' => $user, 'pattern' => $item]) instanceof UnlockedPattern;
    }

    /**
     * The user earns a mon or a pattern (persisted, not flushed). False when they had it already.
     */
    public function unlock(User $user, Mon|BannerPattern $item): bool
    {
        if ($this->has($user, $item)) {
            return false;
        }

        $now = $this->clock->now();
        $this->entityManager->persist($item instanceof Mon ? new UnlockedMon($user, $item, $now) : new UnlockedPattern($user, $item, $now));

        return true;
    }

    /**
     * Forgets what the user has earned (the account is deleted).
     */
    public function forget(User $user): void
    {
        $this->unlockedMonRepository->deleteByUser($user);
        $this->unlockedPatternRepository->deleteByUser($user);
    }

    /**
     * The nobori a user shows in a game: theirs, or for the house bot the mon of the clan of its stronghold (the clan
     * of its deck) on a plain nobori.
     */
    public function bannerInGame(User $user, ?Clan $clan): Banner
    {
        if ($user->getId() === $this->houseBotId) {
            return new Banner($clan instanceof Clan ? Mon::ofClan($clan) : Mon::Enso, BannerPattern::Plain);
        }

        return $user->getBanner();
    }
}
