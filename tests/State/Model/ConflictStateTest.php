<?php

declare(strict_types=1);

namespace App\Tests\State\Model;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\State\Model\ConflictState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConflictStateTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, ?string}>
     */
    public static function results(): iterable
    {
        yield 'higher attacker skill' => [3, 2, 'john'];
        yield 'higher defender skill' => [2, 3, 'jane'];
        yield 'tie' => [2, 2, 'john'];
        yield 'defender with no skill' => [1, 0, 'john'];
        yield 'attacker with no skill' => [0, 1, 'jane'];
        yield 'no skill on either side' => [0, 0, null];
    }

    #[DataProvider('results')]
    public function testTheWinnerIsTheSideWithTheHigherSkill(int $attackerSkill, int $defenderSkill, ?string $winnerId): void
    {
        $conflict = new ConflictState('john', 'jane', Element::Air, ConflictType::Military, 'province');
        $conflict->attackerSkill = $attackerSkill;
        $conflict->defenderSkill = $defenderSkill;

        $conflict->determineWinner();

        self::assertSame($winnerId, $conflict->winnerId);
        self::assertSame($attackerSkill - $defenderSkill, $conflict->excessSkill());
    }
}
