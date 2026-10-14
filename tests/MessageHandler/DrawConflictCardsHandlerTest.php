<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\StackType;
use App\Message\DrawConflictCards;
use App\MessageHandler\DrawConflictCardsHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class DrawConflictCardsHandlerTest extends AbstractKernelTestCase
{
    /**
     * @param array<array-key, string> $deck
     * @param array<array-key, string> $discardPile
     */
    private function draw(array $deck, array $discardPile, int $quantity): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('draw_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->conflictDeck($deck)
                ->conflictDiscardPile($discardPile))
            ->withPlayer('jane')
            ->persist();

        self::service(DrawConflictCardsHandler::class)(new DrawConflictCards(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->player('john')->getId(),
            $quantity,
        ));

        return $scenario;
    }

    private function stackSize(Scenario $scenario, StackType $stackType): int
    {
        return $scenario->player('john')->getUniqueStack($stackType)->getCards()->count();
    }

    public function testDrawingTheLastCardOfTheDeckCostsNoHonor(): void
    {
        $scenario = $this->draw(['01-banzai'], ['01-fine-katana'], 1);

        self::assertSame(1, $this->stackSize($scenario, StackType::Hand));
        self::assertSame(1, $this->stackSize($scenario, StackType::ConflictDiscardPile));
        self::assertSame(10, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testKeepsDrawingFromTheNewDeckOnceTheDeckRunsOut(): void
    {
        $scenario = $this->draw(['01-banzai'], ['01-fine-katana', '01-banzai', '01-fine-katana'], 3);

        self::assertSame(3, $this->stackSize($scenario, StackType::Hand));
        self::assertSame(1, $this->stackSize($scenario, StackType::ConflictDrawDeck));
        self::assertSame(0, $this->stackSize($scenario, StackType::ConflictDiscardPile));
        self::assertSame(5, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testDrawsAsManyCardsAsPossibleWhenTheDiscardPileIsEmptyToo(): void
    {
        $scenario = $this->draw(['01-banzai'], [], 3);

        self::assertSame(1, $this->stackSize($scenario, StackType::Hand));
        self::assertSame(5, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }
}
