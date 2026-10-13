<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Enum\StackType;
use App\Exception\Rules\NoHonorBidException;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;

class CardsDrawnTransitionListenerTest extends AbstractKernelTestCase
{
    private function drawCards(?int $johnBid, ?int $janeBid): Scenario
    {
        $deck = ['01-fine-katana', '01-banzai', '01-fine-katana', '01-banzai', '01-fine-katana'];
        $scenario = $this->scenario()
            ->atPlace('draw_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $johnBid === null ? $p->conflictDeck($deck) : $p->conflictDeck($deck)->honorBid($johnBid))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $janeBid === null ? $p->conflictDeck($deck) : $p->conflictDeck($deck)->honorBid($janeBid))
            ->persist();

        self::service(Registry::class)->get($scenario->game())->apply($scenario->game(), 'cards_drawn');

        return $scenario;
    }

    private function handSize(Scenario $scenario, string $alias): int
    {
        return $scenario->player($alias)->getUniqueStack(StackType::Hand)->getCards()->count();
    }

    public function testEachPlayerDrawsAsManyCardsAsTheirHonorBid(): void
    {
        $scenario = $this->drawCards(5, 2);

        self::assertSame(5, $this->handSize($scenario, 'john'));
        self::assertSame(0, $scenario->player('john')->getUniqueStack(StackType::ConflictDrawDeck)->getCards()->count());
        self::assertSame(2, $this->handSize($scenario, 'jane'));
        self::assertSame(3, $scenario->player('jane')->getUniqueStack(StackType::ConflictDrawDeck)->getCards()->count());
    }

    public function testAPlayerWhoBidZeroDrawsNoCard(): void
    {
        $scenario = $this->drawCards(0, 3);

        self::assertSame(0, $this->handSize($scenario, 'john'));
        self::assertSame(3, $this->handSize($scenario, 'jane'));
    }

    public function testEveryPlayerMustHaveBid(): void
    {
        $this->expectException(NoHonorBidException::class);

        $this->drawCards(3, null);
    }
}
