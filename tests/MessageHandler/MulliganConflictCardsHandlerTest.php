<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\PhysicalCard;
use App\Enum\StackType;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInHandException;
use App\Message\MulliganConflictCards;
use App\MessageHandler\MulliganConflictCardsHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class MulliganConflictCardsHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAt(string $place = 'conflict_mulligan', string $active = 'john'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['katana' => '01-fine-katana', 'banzai' => '01-banzai', 'defeat' => '01-admit-defeat'])
                ->conflictDeck(['01-ornate-fan', '01-way-of-the-crane'])
                ->conflictDiscardPile(['discarded' => '01-voice-of-honor']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['charge' => '01-charge']))
            ->firstPlayer('john')
            ->activePlayer($active)
            ->withGameState(function (GameState $state): void {
                $state->mulligan = true;
            })
            ->persist();
    }

    /**
     * @param list<string> $cards aliases
     */
    private function mulligan(Scenario $scenario, string $player, array $cards): void
    {
        self::service(MulliganConflictCardsHandler::class)(new MulliganConflictCards(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            array_map(fn (string $card): string => $scenario->card($card)->getId(), $cards),
        ));
    }

    /**
     * @return list<string> sorted codes
     */
    private function codes(Scenario $scenario, string $player, StackType $type): array
    {
        $codes = array_map(fn (PhysicalCard $card): string => $card->getLogicalCard()->getId(), $scenario->player($player)->getUniqueStack($type)->getOrderedCards());
        sort($codes);

        return $codes;
    }

    public function testTheCardsAreReplacedThenShuffledBackIntoTheDeck(): void
    {
        $scenario = $this->scenarioAt();

        $this->mulligan($scenario, 'john', ['banzai', 'defeat']);

        self::assertSame(['01-fine-katana', '01-ornate-fan', '01-way-of-the-crane'], $this->codes($scenario, 'john', StackType::Hand));
        self::assertSame(['01-admit-defeat', '01-banzai'], $this->codes($scenario, 'john', StackType::ConflictDrawDeck));
        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([$scenario->player('jane')->getId()], $state->activePlayerIds);
        // jane does not learn the cards
        self::assertSame([
            'john draws a hidden card',
            'john draws a hidden card',
            "a hidden card is shuffled from john's hand into john's conflict deck",
            "a hidden card is shuffled from john's hand into john's conflict deck",
            "john's conflict deck is shuffled",
            "john mulligans 2 of their cards into john's conflict deck",
        ], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testNoCardKeepsTheCards(): void
    {
        $scenario = $this->scenarioAt();

        $this->mulligan($scenario, 'john', []);

        self::assertSame(['01-admit-defeat', '01-banzai', '01-fine-katana'], $this->codes($scenario, 'john', StackType::Hand));
        self::assertSame(['john keeps their cards'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testTheLastPlayerEndsTheStep(): void
    {
        $scenario = $this->scenarioAt(active: 'jane');

        $this->mulligan($scenario, 'jane', []);

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([], $state->activePlayerIds);
    }

    public function testOnlyTheActivePlayerMulligans(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(NotActivePlayerException::class);
        $this->mulligan($scenario, 'jane', []);
    }

    public function testOnlyDuringTheConflictMulligan(): void
    {
        $scenario = $this->scenarioAt('dynasty_mulligan');

        $this->expectException(WorkflowException::class);
        $this->mulligan($scenario, 'john', []);
    }

    public function testACardOutsideTheHandIsRefused(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(NotInHandException::class);
        $this->mulligan($scenario, 'john', ['discarded']);
    }

    public function testACardOfTheOpponentsHandIsRefused(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(NotInHandException::class);
        $this->mulligan($scenario, 'john', ['charge']);
    }
}
