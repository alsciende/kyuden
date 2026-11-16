<?php

declare(strict_types=1);

namespace App\Tests\Trophy;

use App\Enum\Trophy;
use App\Enum\VictoryCondition;
use App\Trophy\AbstractClanTrophy;
use App\Trophy\TrophyInterface;
use PHPUnit\Framework\TestCase;

class TrophyRulesTest extends TestCase
{
    public function testEachTrophyHasItsRuleNamedAfterIt(): void
    {
        // src/Trophy holds the interface, the base of the trophies of a clan and one rule per trophy only
        $trophies = [];
        foreach (glob(\dirname(__DIR__, 2) . '/src/Trophy/*.php') ?: [] as $file) {
            $className = 'App\\Trophy\\' . basename($file, '.php');
            if (\in_array($className, [TrophyInterface::class, AbstractClanTrophy::class], true)) {
                continue;
            }
            self::assertTrue(is_subclass_of($className, TrophyInterface::class), "{$className} is not the rule of a trophy.");
            $trophy = $className::getTrophy();
            self::assertSame('App\\Trophy\\' . $trophy->name, $className, 'named after its trophy');
            $trophies[] = $trophy;
        }

        self::assertEqualsCanonicalizing(
            array_column(Trophy::cases(), 'value'),
            array_column($trophies, 'value'),
            'one rule per trophy',
        );
        self::assertCount(\count(Trophy::cases()), $trophies);
    }

    public function testAGameIsPlayedToItsEndByHonorOrByBreakingTheStrongholdProvince(): void
    {
        self::assertSame(
            [VictoryCondition::HonorWin, VictoryCondition::HonorLoss, VictoryCondition::StrongholdProvinceBroken],
            array_values(array_filter(VictoryCondition::cases(), static fn (VictoryCondition $condition): bool => $condition->isPlayedToTheEnd())),
        );
    }
}
