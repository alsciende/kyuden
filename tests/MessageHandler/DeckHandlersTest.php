<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Enum\DeckPosition;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Exception\Rules\WrongCardSideException;
use App\Message\AddCardToHand;
use App\Message\DiscardCard;
use App\Message\PutCardOnDeck;
use App\Message\ShuffleIntoDeck;
use App\MessageHandler\AddCardToHandHandler;
use App\MessageHandler\DiscardCardHandler;
use App\MessageHandler\PutCardOnDeckHandler;
use App\MessageHandler\ShuffleIntoDeckHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

/**
 * Cards moving to a hand or into a deck, e.g. once a player has looked at them.
 */
class DeckHandlersTest extends AbstractKernelTestCase
{
    private function scenarioWithDecks(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                // from the bottom to the top
                ->conflictDeck(['01-banzai', '01-fine-katana', '01-ornate-fan'])
                ->conflictDiscardPile(['ancestral' => '01-ancestral-daisho'])
                ->hand(['outwit' => '01-outwit'])
                ->inPlay(['toturi' => '01-akodo-toturi'])
                ->dynastyDeck(['01-doji-whisperer', '01-eager-scout'])
                ->province('01-entrenched-position', as: 'province', dynasty: '01-brash-samurai', dynastyAs: 'samurai', dynastyFaceup: true))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p)
            ->persist();
    }

    /**
     * @return list<string> the names of the cards of a stack of john, from the bottom to the top
     */
    private function names(Scenario $scenario, StackType $type): array
    {
        return array_map(
            fn (PhysicalCard $card): string => $card->getLogicalCard()->getName(),
            $scenario->player('john')->getUniqueStack($type)->getOrderedCards(),
        );
    }

    private function deckCard(Scenario $scenario, StackType $deck, int $fromTop): PhysicalCard
    {
        return $scenario->player('john')->getUniqueStack($deck)->getTopCards($fromTop + 1)[$fromTop];
    }

    private function sender(Scenario $scenario): string
    {
        return $scenario->player('jane')->getId();
    }

    public function testAddsACardOfTheDeckToTheHandOfItsOwner(): void
    {
        $scenario = $this->scenarioWithDecks();
        $katana = $this->deckCard($scenario, StackType::ConflictDrawDeck, 1);

        self::service(AddCardToHandHandler::class)(new AddCardToHand($scenario->game()->getId(), $this->sender($scenario), $katana->getId()));

        self::assertSame(['Outwit', 'Fine Katana'], $this->names($scenario, StackType::Hand));
        self::assertSame(['Banzai!', 'Ornate Fan'], $this->names($scenario, StackType::ConflictDrawDeck));
        self::assertSame(Orientation::FaceUp, $katana->getOrientation());
        self::assertSame(["Fine Katana is added from john's conflict deck to john's hand"], $this->logLines($scenario->game(), $scenario->player('john')));
        self::assertSame(["a hidden card is added from john's conflict deck to john's hand"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testAddsACardOfTheDiscardPileToTheHandOfItsOwner(): void
    {
        $scenario = $this->scenarioWithDecks();

        self::service(AddCardToHandHandler::class)(new AddCardToHand($scenario->game()->getId(), $this->sender($scenario), $scenario->card('ancestral')->getId()));

        self::assertSame(['Outwit', 'Ancestral Daishō'], $this->names($scenario, StackType::Hand));
    }

    public function testRejectsAddingADynastyCardToAHand(): void
    {
        $scenario = $this->scenarioWithDecks();

        $this->expectException(WrongCardSideException::class);

        self::service(AddCardToHandHandler::class)(new AddCardToHand($scenario->game()->getId(), $this->sender($scenario), $scenario->card('samurai')->getId()));
    }

    public function testRejectsAddingACardOfTheHandToAHand(): void
    {
        $scenario = $this->scenarioWithDecks();

        $this->expectException(NotEnabledTransitionException::class);

        self::service(AddCardToHandHandler::class)(new AddCardToHand($scenario->game()->getId(), $this->sender($scenario), $scenario->card('outwit')->getId()));
    }

    public function testPutsACardOfTheDeckAtItsBottom(): void
    {
        $scenario = $this->scenarioWithDecks();
        $fan = $this->deckCard($scenario, StackType::ConflictDrawDeck, 0);

        self::service(PutCardOnDeckHandler::class)(new PutCardOnDeck($scenario->game()->getId(), $this->sender($scenario), $fan->getId(), DeckPosition::Bottom));

        self::assertSame(['Ornate Fan', 'Banzai!', 'Fine Katana'], $this->names($scenario, StackType::ConflictDrawDeck));
    }

    public function testPutsACardOfTheHandOnTopOfTheDeckFaceDown(): void
    {
        $scenario = $this->scenarioWithDecks();

        self::service(PutCardOnDeckHandler::class)(new PutCardOnDeck($scenario->game()->getId(), $this->sender($scenario), $scenario->card('outwit')->getId(), DeckPosition::Top));

        self::assertSame(['Banzai!', 'Fine Katana', 'Ornate Fan', 'Outwit'], $this->names($scenario, StackType::ConflictDrawDeck));
        self::assertSame(Orientation::FaceDown, $scenario->card('outwit')->getOrientation());
        self::assertSame(["a hidden card is put from john's hand on top of john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testPutsACharacterInPlayOnTopOfTheDeckOfItsSide(): void
    {
        $scenario = $this->scenarioWithDecks();

        self::service(PutCardOnDeckHandler::class)(new PutCardOnDeck($scenario->game()->getId(), $this->sender($scenario), $scenario->card('toturi')->getId(), DeckPosition::Top));

        self::assertSame(['Doji Whisperer', 'Eager Scout', 'Akodo Toturi'], $this->names($scenario, StackType::DynastyDrawDeck));
        self::assertSame(StackType::DynastyDrawDeck, $scenario->card('toturi')->getStack()->getType());
    }

    public function testRefillsTheProvinceACardIsPutOnTheDeckFrom(): void
    {
        $scenario = $this->scenarioWithDecks();
        $province = $scenario->card('province')->getStack();

        self::service(PutCardOnDeckHandler::class)(new PutCardOnDeck($scenario->game()->getId(), $this->sender($scenario), $scenario->card('samurai')->getId(), DeckPosition::Bottom));

        self::assertSame(['Brash Samurai', 'Doji Whisperer'], $this->names($scenario, StackType::DynastyDrawDeck));
        $refill = $province->getTopCard();
        self::assertInstanceOf(DynastyCard::class, $refill);
        self::assertSame('Eager Scout', $refill->getLogicalCard()->getName());
    }

    public function testShufflesACardIntoTheDeck(): void
    {
        $scenario = $this->scenarioWithDecks();
        $deck = $scenario->player('john')->getUniqueStack(StackType::ConflictDrawDeck);
        $before = array_map(fn (PhysicalCard $card): string => $card->getId(), $deck->getOrderedCards());

        self::service(ShuffleIntoDeckHandler::class)(new ShuffleIntoDeck($scenario->game()->getId(), $this->sender($scenario), $scenario->card('outwit')->getId()));

        $names = $this->names($scenario, StackType::ConflictDrawDeck);
        sort($names);
        self::assertSame(['Banzai!', 'Fine Katana', 'Ornate Fan', 'Outwit'], $names);
        self::assertSame([], $this->names($scenario, StackType::Hand));
        // every card of the deck has a new id
        self::assertSame([], array_intersect($before, array_map(fn (PhysicalCard $card): string => $card->getId(), $deck->getOrderedCards())));
        self::assertContainsOnlyInstancesOf(ConflictCard::class, $deck->getCards());
        self::assertSame([
            "a hidden card is shuffled from john's hand into john's conflict deck",
            "john's conflict deck is shuffled",
        ], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testDiscardsACardOfTheDynastyDeck(): void
    {
        $scenario = $this->scenarioWithDecks();

        self::service(DiscardCardHandler::class)(new DiscardCard($scenario->game()->getId(), $this->sender($scenario), $this->deckCard($scenario, StackType::DynastyDrawDeck, 0)->getId()));

        self::assertSame(['Eager Scout'], $this->names($scenario, StackType::DynastyDiscardPile));
        self::assertSame(['Doji Whisperer'], $this->names($scenario, StackType::DynastyDrawDeck));
    }
}
