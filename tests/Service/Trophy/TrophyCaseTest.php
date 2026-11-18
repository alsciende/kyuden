<?php

declare(strict_types=1);

namespace App\Tests\Service\Trophy;

use App\Entity\User;
use App\Enum\BannerPattern;
use App\Enum\Clan;
use App\Enum\Mon;
use App\Enum\Trophy;
use App\Service\Nobori\NoboriCollection;
use App\Service\Trophy\TrophyCase;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class TrophyCaseTest extends AbstractKernelTestCase
{
    private function user(string $id): User
    {
        $user = self::service(EntityManagerInterface::class)->find(User::class, $id);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    public function testEachTrophyOfAClanGivesItsMonAndTheOthersAPattern(): void
    {
        foreach (Clan::greatClans() as $clan) {
            self::assertSame(Mon::ofClan($clan), Trophy::ofClan($clan)?->getReward());
        }
        self::assertNull(Trophy::ofClan(Clan::Neutral));
        self::assertSame(BannerPattern::Plain, Trophy::HouseBotGame->getReward());
        self::assertSame(BannerPattern::Tatejima, Trophy::TournamentGame->getReward());
        self::assertSame(BannerPattern::Dandara, Trophy::TournamentWin->getReward());
        self::assertCount(10, Trophy::cases());
    }

    public function testAUserEarnsATrophyOnceWithItsReward(): void
    {
        $case = self::service(TrophyCase::class);
        $jane = $this->user('jane');
        $at = new \DateTimeImmutable('2026-11-14 12:00:00');

        self::assertTrue($case->award($jane, Trophy::ClanLion, $at));
        self::assertFalse($case->award($jane, Trophy::ClanLion, $at->modify('+1 day')), 'not flushed yet');
        self::service(EntityManagerInterface::class)->flush();
        self::service(EntityManagerInterface::class)->clear();

        $jane = $this->user('jane');
        self::assertFalse($case->award($jane, Trophy::ClanLion, $at->modify('+1 day')));
        $earned = $case->earned($jane);
        self::assertSame([Trophy::ClanLion->value], array_keys($earned));
        self::assertEquals($at, $earned[Trophy::ClanLion->value]->getEarnedAt(), 'the first time');
        self::assertNull($earned[Trophy::ClanLion->value]->getGame());
        self::assertSame([Mon::Enso, Mon::Lion], self::service(NoboriCollection::class)->mons($jane));
    }

    public function testATrophyWhoseRewardIsEarnedAlreadyIsStillEarned(): void
    {
        $case = self::service(TrophyCase::class);
        $john = $this->user('john');

        // john has the plain pattern from the fixtures
        self::assertTrue($case->award($john, Trophy::HouseBotGame, new \DateTimeImmutable()));
        self::assertTrue($case->has($john, Trophy::HouseBotGame));
    }

    public function testTheTrophiesOfAUserAreForgotten(): void
    {
        $case = self::service(TrophyCase::class);
        $case->award($this->user('jane'), Trophy::ClanCrab, new \DateTimeImmutable());
        $case->award($this->user('harry'), Trophy::ClanCrab, new \DateTimeImmutable());
        self::service(EntityManagerInterface::class)->flush();

        $case->forget($this->user('jane'));
        self::service(EntityManagerInterface::class)->clear();

        self::assertSame([], $case->earned($this->user('jane')));
        self::assertCount(1, $case->earned($this->user('harry')));
    }

    public function testEachTrophyIsKnownToTheWebClient(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);
        $client = (string) file_get_contents("{$projectDir}/front/src/api/client.ts");

        // the names and goals are written by data/trophies.ts, from the values of the type TrophyId
        foreach (Trophy::cases() as $trophy) {
            self::assertStringContainsString("| '{$trophy->value}'", $client, "the trophy {$trophy->value}");
        }
    }
}
