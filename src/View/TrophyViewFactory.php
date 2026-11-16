<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\EarnedTrophy;
use App\Entity\User;
use App\Enum\BannerPattern;
use App\Enum\Mon;
use App\Enum\Trophy;
use App\Service\Trophy\TrophyCase;
use App\View\Model\TrophyView;

/**
 * Every trophy of the catalogue, in its order, as a user has earned it or not.
 */
readonly class TrophyViewFactory
{
    public function __construct(
        private TrophyCase $trophyCase,
    ) {
    }

    /**
     * @return list<TrophyView>
     */
    public function createAll(User $user): array
    {
        $earned = $this->trophyCase->earned($user);

        return array_map(static function (Trophy $trophy) use ($earned): TrophyView {
            $earnedTrophy = $earned[$trophy->value] ?? null;
            $reward = $trophy->getReward();

            return new TrophyView(
                trophy: $trophy,
                rewardMon: $reward instanceof Mon ? $reward : null,
                rewardPattern: $reward instanceof BannerPattern ? $reward : null,
                earned: $earnedTrophy instanceof EarnedTrophy,
                earnedAt: $earnedTrophy?->getEarnedAt(),
                gameId: $earnedTrophy?->getGame()?->getId(),
            );
        }, Trophy::cases());
    }
}
