<?php

declare(strict_types=1);

namespace App\Tests\Service\Nobori;

use App\Entity\User;
use App\Enum\BannerPattern;
use App\Enum\Clan;
use App\Enum\Mon;
use App\Model\Banner;
use App\Service\Nobori\NoboriCollection;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class NoboriCollectionTest extends AbstractKernelTestCase
{
    private function user(string $id): User
    {
        $user = self::service(EntityManagerInterface::class)->find(User::class, $id);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    public function testEveryPlayerHasTheEnsoWithoutPattern(): void
    {
        $collection = self::service(NoboriCollection::class);

        self::assertSame([Mon::Enso], $collection->mons($this->user('jane')));
        self::assertSame([BannerPattern::None], $collection->patterns($this->user('jane')));
        self::assertTrue($collection->has($this->user('harry'), Mon::Enso));
        self::assertFalse($collection->has($this->user('harry'), BannerPattern::Plain));
    }

    public function testWhatIsEarnedComesInTheOrderOfTheEnums(): void
    {
        $collection = self::service(NoboriCollection::class);
        $john = $this->user('john');

        self::assertSame([Mon::Enso, Mon::Crab, Mon::Phoenix], $collection->mons($john));
        self::assertSame([BannerPattern::None, BannerPattern::Plain, BannerPattern::Asanoha, BannerPattern::Seigaiha], $collection->patterns($john));
    }

    public function testAUserEarnsAMonOrAPatternOnce(): void
    {
        $collection = self::service(NoboriCollection::class);
        $jane = $this->user('jane');

        self::assertTrue($collection->unlock($jane, Mon::Unicorn));
        self::assertFalse($collection->unlock($jane, Mon::Unicorn), 'not flushed yet');
        self::assertTrue($collection->unlock($jane, BannerPattern::Nami));
        self::assertFalse($collection->unlock($jane, Mon::Enso), 'every player has it');
        self::service(EntityManagerInterface::class)->flush();

        self::assertFalse($collection->unlock($jane, BannerPattern::Nami));
        self::assertSame([Mon::Enso, Mon::Unicorn], $collection->mons($jane));
        self::assertSame([BannerPattern::None, BannerPattern::Nami], $collection->patterns($jane));
    }

    public function testForgettingLeavesTheEnsoWithoutPattern(): void
    {
        $collection = self::service(NoboriCollection::class);
        $john = $this->user('john');

        $collection->forget($john);

        self::assertSame([Mon::Enso], $collection->mons($john));
        self::assertSame([BannerPattern::None], $collection->patterns($john));
    }

    public function testAPlayerShowsTheirOwnNoboriInAGame(): void
    {
        $john = $this->user('john');
        $john->changeBanner(new Banner(Mon::Crab, BannerPattern::Seigaiha));

        self::assertEquals(new Banner(Mon::Crab, BannerPattern::Seigaiha), self::service(NoboriCollection::class)->bannerInGame($john, Clan::Lion));
    }

    public function testTheHouseBotShowsTheMonOfItsClanOnAPlainNobori(): void
    {
        $collection = self::service(NoboriCollection::class);
        $bot = $this->user('kyuden-bot');

        self::assertEquals(new Banner(Mon::Scorpion, BannerPattern::Plain), $collection->bannerInGame($bot, Clan::Scorpion));
        self::assertEquals(new Banner(Mon::Enso, BannerPattern::Plain), $collection->bannerInGame($bot, null));
        self::assertEquals(new Banner(Mon::Enso, BannerPattern::Plain), $collection->bannerInGame($bot, Clan::Neutral));
    }

    public function testEachMonAndEachTiledPatternHasItsDrawingInTheWebClient(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);

        foreach (Mon::cases() as $mon) {
            // the ensō is the mon of the neutral cards
            $file = $mon === Mon::Enso ? 'neutral' : $mon->value;
            self::assertFileExists("{$projectDir}/front/src/assets/mons/{$file}.svg", "the mon {$mon->value}");
        }
        foreach (BannerPattern::cases() as $pattern) {
            if ($pattern->hasTile()) {
                self::assertFileExists("{$projectDir}/front/src/assets/patterns/{$pattern->value}.svg", "the pattern {$pattern->value}");
            }
        }
    }
}
