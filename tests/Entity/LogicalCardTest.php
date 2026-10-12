<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\LogicalCard;
use App\Enum\Side;
use App\Enum\StackType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LogicalCardTest extends TestCase
{
    /**
     * @return iterable<string, array{Side, StackType}>
     */
    public static function initialStackTypes(): iterable
    {
        yield 'conflict' => [Side::Conflict, StackType::ConflictDrawDeck];
        yield 'dynasty' => [Side::Dynasty, StackType::DynastyDrawDeck];
        yield 'role' => [Side::Role, StackType::Role];
        yield 'province' => [Side::Province, StackType::StrongholdProvince];
        yield 'stronghold' => [Side::Stronghold, StackType::StrongholdProvince];
    }

    #[DataProvider('initialStackTypes')]
    public function testInitialStackTypeDependsOnTheSide(Side $side, StackType $stackType): void
    {
        $card = new LogicalCard();
        $card->setSide($side);

        self::assertSame($stackType, $card->getInitialStackType());
    }
}
