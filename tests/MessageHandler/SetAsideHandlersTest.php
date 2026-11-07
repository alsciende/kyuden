<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Enum\StackType;
use App\Exception\Rules\CannotHostCardException;
use App\Message\AddCardToHand;
use App\Message\BowCard;
use App\Message\DiscardCard;
use App\Message\PlaceCardUnder;
use App\Message\PlayActionEvent;
use App\Message\PlayCharacter;
use App\Message\PutCardIntoProvince;
use App\Message\PutCharacterIntoPlay;
use App\Message\RemoveFromGame;
use App\MessageHandler\AddCardToHandHandler;
use App\MessageHandler\BowCardHandler;
use App\MessageHandler\DiscardCardHandler;
use App\MessageHandler\PlaceCardUnderHandler;
use App\MessageHandler\PlayActionEventHandler;
use App\MessageHandler\PlayCharacterHandler;
use App\MessageHandler\PutCardIntoProvinceHandler;
use App\MessageHandler\PutCharacterIntoPlayHandler;
use App\MessageHandler\RemoveFromGameHandler;
use App\Service\ProvinceRefiller;
use App\Service\SetAsideStacks;
use App\State\Manager\CardStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\View\GameViewFactory;
use App\View\Model\StackView;
use App\View\Visibility;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

/**
 * Cards set aside out of play: removed from the game (RemoveFromGame) or placed under a card (PlaceCardUnder), then
 * brought back by the other effects.
 */
class SetAsideHandlersTest extends AbstractKernelTestCase
{
    private function scenarioAt(string $place = 'conflict_action_window'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->fate(5)
                ->stronghold('01-yojin-no-shiro', as: 'stronghold')
                ->strongholdProvince('01-shameful-display', as: 'stronghold-province')
                ->hand(['ambusher' => '01-hiruma-ambusher', 'banzai' => '01-banzai'])
                ->conflictDeck(['deck-katana' => '01-fine-katana', 'deck-banzai' => '01-banzai'])
                ->dynastyDeck(['deck-scout' => '01-eager-scout'])
                ->dynastyDiscardPile(['keeper' => '01-keeper-initiate'])
                ->province('01-entrenched-position', as: 'province', dynasty: '04-back-alley-hideaway', dynastyAs: 'hideaway', dynastyFaceup: true)
                ->province('01-pilgrimage', as: 'other', dynasty: '01-ikoma-eiji', dynastyAs: 'eiji', dynastyFaceup: true)
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'], fate: 2, personalHonor: PersonalHonor::Honored))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->province('01-the-art-of-peace', as: 'jane-province', orientation: Orientation::FaceUp))
            ->conflict('jane', 'jane-province')
            ->activePlayer('jane')
            ->persist();
    }

    private function remove(Scenario $scenario, string $card, bool $faceDown = false, string $sender = 'john'): void
    {
        self::service(RemoveFromGameHandler::class)(new RemoveFromGame(
            $scenario->game()->getId(),
            $scenario->player($sender)->getId(),
            $scenario->card($card)->getId(),
            $faceDown,
        ));
    }

    private function placeUnder(Scenario $scenario, string $card, string $host, bool $faceDown = true, string $sender = 'john'): void
    {
        self::service(PlaceCardUnderHandler::class)(new PlaceCardUnder(
            $scenario->game()->getId(),
            $scenario->player($sender)->getId(),
            $scenario->card($card)->getId(),
            $scenario->card($host)->getId(),
            $faceDown,
        ));
    }

    private function seesFace(Scenario $scenario, string $card, ?string $viewer): bool
    {
        $card = $scenario->card($card);

        return self::service(Visibility::class)->isCardInStackVisibleTo($card, $card->getStack(), $viewer === null ? null : $scenario->player($viewer));
    }

    public function testACardRemovedFaceUpGoesToTheCardsRemovedFromTheGameOfItsOwner(): void
    {
        // Karmic Balance, Kitsuki Kagi: the opponent removes it, its owner keeps it
        $scenario = $this->scenarioAt();

        $this->remove($scenario, 'banzai', sender: 'jane');

        $banzai = $scenario->card('banzai');
        self::assertSame(ConflictCard::STATE_REMOVED_FROM_GAME, $banzai->getCurrentPlace());
        self::assertSame(Orientation::FaceUp, $banzai->getOrientation());
        self::assertSame($scenario->player('john'), $banzai->getStack()->getPlayer());
        self::assertSame(StackType::RemovedFromGame, $banzai->getStack()->getType());
        self::assertTrue($this->seesFace($scenario, 'banzai', 'jane'));
        self::assertTrue($this->seesFace($scenario, 'banzai', null));
        self::assertSame(["Banzai! is removed from the game from john's hand"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testTheCardsRemovedFromTheGameOfAPlayerShareTheirStack(): void
    {
        $scenario = $this->scenarioAt();

        $this->remove($scenario, 'banzai');
        $this->remove($scenario, 'keeper');

        self::assertSame($scenario->card('banzai')->getStack(), $scenario->card('keeper')->getStack());
        self::assertSame(DynastyCard::STATE_REMOVED_FROM_GAME, $scenario->card('keeper')->getCurrentPlace());
        self::assertCount(1, $scenario->player('john')->getStacks(StackType::RemovedFromGame));
    }

    public function testACardRemovedFaceDownIsKnownToTheSenderOnly(): void
    {
        // Stolen Secrets: a card of the opponent's deck, which its owner does not know
        $scenario = $this->scenarioAt();

        $this->remove($scenario, 'deck-banzai', faceDown: true, sender: 'jane');

        $banzai = $scenario->card('deck-banzai');
        self::assertSame(ConflictCard::STATE_REMOVED_FROM_GAME, $banzai->getCurrentPlace());
        self::assertSame(Orientation::FaceDown, $banzai->getOrientation());
        self::assertSame($scenario->player('jane'), $banzai->getStack()->getPlayer());
        self::assertTrue($this->seesFace($scenario, 'deck-banzai', 'jane'));
        self::assertFalse($this->seesFace($scenario, 'deck-banzai', 'john'));
        self::assertFalse($this->seesFace($scenario, 'deck-banzai', null));
        self::assertSame(["Banzai! is removed from the game facedown from john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('jane')));
        self::assertSame(["a facedown card is removed from the game facedown from john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testACharacterRemovedFromTheGameLeavesPlay(): void
    {
        // Isawa Ujina, Shadow Step: its fate, its status and its attachments leave with it
        $scenario = $this->scenarioAt();

        $this->remove($scenario, 'toturi');

        $toturi = $scenario->card('toturi');
        self::assertSame(DynastyCard::STATE_REMOVED_FROM_GAME, $toturi->getCurrentPlace());
        self::assertSame(0, self::service(CardStateManager::class)->getState($toturi)->getFate());
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('katana')->getCurrentPlace());
        self::assertSame(11, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testAProvinceACardIsRemovedFromIsRefilled(): void
    {
        $scenario = $this->scenarioAt();

        $this->remove($scenario, 'eiji');

        $this->assertRefillScheduled($scenario->card('other'));
    }

    public function testRejectsACardAlreadyRemoved(): void
    {
        $scenario = $this->scenarioAt();
        $this->remove($scenario, 'banzai');

        $this->expectException(NotEnabledTransitionException::class);

        $this->remove($scenario, 'banzai', faceDown: true);
    }

    public function testRejectsAProvince(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(\LogicException::class);

        $this->remove($scenario, 'province');
    }

    public function testACharacterRemovedFromTheGameIsPutIntoPlay(): void
    {
        // Shadow Step: removed, then put into play
        $scenario = $this->scenarioAt();
        $this->remove($scenario, 'toturi');

        self::service(PutCharacterIntoPlayHandler::class)(new PutCharacterIntoPlay(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('toturi')->getId(),
            false,
        ));

        self::assertSame(DynastyCard::STATE_IN_PLAY, $scenario->card('toturi')->getCurrentPlace());
        self::assertSame(PersonalHonor::Ordinary, self::service(CardStateManager::class)->getState($scenario->card('toturi'))->getPersonalHonor());
    }

    public function testACardRemovedFaceDownIsPlayedByTheSender(): void
    {
        // Stolen Secrets: played as if it were in the hand of the sender, then in the discard pile of its owner
        $scenario = $this->scenarioAt();
        $this->remove($scenario, 'deck-banzai', faceDown: true, sender: 'jane');

        self::service(PlayActionEventHandler::class)(new PlayActionEvent(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card('deck-banzai')->getId(),
            1,
        ));

        self::assertSame($scenario->player('john')->getUniqueStack(StackType::ConflictDiscardPile), $scenario->card('deck-banzai')->getStack());
        self::assertSame(4, $this->playerStateManager->getState($scenario->player('jane'))->getFate());
    }

    public function testACharacterRemovedFromTheGameIsPlayed(): void
    {
        // Trading on the Sand Road: either player plays the removed cards
        $scenario = $this->scenarioAt();
        $this->remove($scenario, 'ambusher');

        self::service(PlayCharacterHandler::class)(new PlayCharacter(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card('ambusher')->getId(),
            2,
            0,
            0,
            false,
        ));

        self::assertSame(ConflictCard::STATE_IN_PLAY, $scenario->card('ambusher')->getCurrentPlace());
        self::assertSame($scenario->player('jane'), $scenario->card('ambusher')->getStack()->getPlayer());
    }

    public function testADynastyCardRemovedFromTheGameIsPutIntoAProvince(): void
    {
        $scenario = $this->scenarioAt();
        $this->remove($scenario, 'keeper');

        self::service(PutCardIntoProvinceHandler::class)(new PutCardIntoProvince(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('keeper')->getId(),
            $scenario->card('province')->getId(),
            true,
        ));

        self::assertSame($scenario->card('province')->getStack(), $scenario->card('keeper')->getStack());
    }

    public function testACardIsPlacedFaceDownUnderACharacter(): void
    {
        // Daidoji Uji: cards of the deck under him, known to his owner only
        $scenario = $this->scenarioAt();

        $this->placeUnder($scenario, 'deck-katana', 'toturi');

        $katana = $scenario->card('deck-katana');
        self::assertSame(ConflictCard::STATE_UNDERNEATH, $katana->getCurrentPlace());
        self::assertSame(Orientation::FaceDown, $katana->getOrientation());
        self::assertSame($scenario->card('toturi'), $katana->getStack()->getHost());
        self::assertSame($scenario->player('john'), $katana->getStack()->getPlayer());
        self::assertTrue($this->seesFace($scenario, 'deck-katana', 'john'));
        self::assertFalse($this->seesFace($scenario, 'deck-katana', 'jane'));
        self::assertSame(["Fine Katana is placed facedown from john's conflict deck under Akodo Toturi"], $this->logLines($scenario->game(), $scenario->player('john')));
        self::assertSame(["a facedown card is placed facedown from john's conflict deck under Akodo Toturi"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testTheCardsUnderACardShareTheirStack(): void
    {
        $scenario = $this->scenarioAt();

        $this->placeUnder($scenario, 'deck-katana', 'toturi');
        $this->placeUnder($scenario, 'deck-banzai', 'toturi');

        self::assertSame($scenario->card('deck-katana')->getStack(), $scenario->card('deck-banzai')->getStack());
        self::assertSame([$scenario->card('deck-katana'), $scenario->card('deck-banzai')], $scenario->card('deck-katana')->getStack()->getOrderedCards());
    }

    public function testTheCardsUnderACardAreInTheViewWithTheirHost(): void
    {
        $scenario = $this->scenarioAt();

        $this->placeUnder($scenario, 'deck-katana', 'toturi');

        $view = self::service(GameViewFactory::class)->create($scenario->game(), $scenario->player('jane'));
        $john = $view->players[0];
        $stacks = array_values(array_filter($john->stacks, fn (StackView $stack): bool => $stack->type === 'underneath'));
        self::assertCount(1, $stacks);
        self::assertSame($scenario->card('toturi')->getId(), $stacks[0]->hostId);
        self::assertNotNull($stacks[0]->cards);
        self::assertSame($scenario->card('deck-katana')->getId(), $stacks[0]->cards[0]->id);
        self::assertNull($stacks[0]->cards[0]->code);
    }

    public function testTheHandIsSetAsideUnderTheStrongholdThenAddedBack(): void
    {
        // Under Siege: the defending player sets their hand aside, then adds it back to their hand
        $scenario = $this->scenarioAt();

        $this->placeUnder($scenario, 'banzai', 'stronghold', sender: 'jane');
        $this->placeUnder($scenario, 'ambusher', 'stronghold', sender: 'jane');

        self::assertSame([], $scenario->player('john')->getUniqueStack(StackType::Hand)->getOrderedCards());
        self::assertTrue($this->seesFace($scenario, 'banzai', 'john'));
        self::assertFalse($this->seesFace($scenario, 'banzai', 'jane'));

        self::service(AddCardToHandHandler::class)(new AddCardToHand(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('banzai')->getId(),
        ));

        self::assertSame(ConflictCard::STATE_HAND, $scenario->card('banzai')->getCurrentPlace());
    }

    public function testAHoldingIsPutFromUnderTheStrongholdProvinceIntoAProvince(): void
    {
        // Kaiu Shihobu
        $scenario = $this->scenarioAt();
        $this->placeUnder($scenario, 'keeper', 'stronghold-province');

        self::service(PutCardIntoProvinceHandler::class)(new PutCardIntoProvince(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('keeper')->getId(),
            $scenario->card('other')->getId(),
            true,
        ));

        self::assertSame($scenario->card('other')->getStack(), $scenario->card('keeper')->getStack());
    }

    public function testACharacterIsPlayedFromUnderAHolding(): void
    {
        // Back-Alley Hideaway: the character leaves play facedown onto the holding, then is played from there
        $scenario = $this->scenarioAt();
        $this->placeUnder($scenario, 'toturi', 'hideaway');

        self::assertSame(DynastyCard::STATE_UNDERNEATH, $scenario->card('toturi')->getCurrentPlace());

        self::service(PlayCharacterHandler::class)(new PlayCharacter(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card('toturi')->getId(),
            0,
            0,
            0,
            false,
        ));

        self::assertSame(DynastyCard::STATE_IN_PLAY, $scenario->card('toturi')->getCurrentPlace());
    }

    public function testTheCardsUnderACardAreDiscardedWhenItLeavesPlay(): void
    {
        $scenario = $this->scenarioAt();
        $this->placeUnder($scenario, 'deck-katana', 'toturi');
        $stack = $scenario->card('deck-katana')->getStack();

        self::service(DiscardCardHandler::class)(new DiscardCard(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('toturi')->getId(),
        ));

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('deck-katana')->getCurrentPlace());
        self::assertNotContains($stack, $scenario->player('john')->getStacks());
        self::assertNull(self::service(SetAsideStacks::class)->findUnderneath($scenario->card('toturi')));
    }

    public function testTheCardsUnderAHoldingAreDiscardedWhenItIsDiscarded(): void
    {
        $scenario = $this->scenarioAt();
        $this->placeUnder($scenario, 'toturi', 'hideaway');

        self::service(DiscardCardHandler::class)(new DiscardCard(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('hideaway')->getId(),
        ));

        self::assertSame(DynastyCard::STATE_DISCARD_PILE, $scenario->card('toturi')->getCurrentPlace());
        self::service(ProvinceRefiller::class)->refillScheduled($scenario->game());
    }

    public function testTheCardsUnderACardStayWhenItIsBowed(): void
    {
        $scenario = $this->scenarioAt();
        $this->placeUnder($scenario, 'deck-katana', 'toturi');

        self::service(BowCardHandler::class)(new BowCard(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('toturi')->getId(),
        ));

        self::assertSame(ConflictCard::STATE_UNDERNEATH, $scenario->card('deck-katana')->getCurrentPlace());
    }

    public function testACardRemovedFromTheGameIsPlacedUnderACard(): void
    {
        $scenario = $this->scenarioAt();
        $this->remove($scenario, 'banzai');

        $this->placeUnder($scenario, 'banzai', 'toturi', faceDown: false);

        self::assertSame(ConflictCard::STATE_UNDERNEATH, $scenario->card('banzai')->getCurrentPlace());
        self::assertTrue($this->seesFace($scenario, 'banzai', 'jane'));
    }

    public function testACardUnderACardIsRemovedFromTheGame(): void
    {
        // Daidoji Uji leaving play: the facedown cards under him are removed from the game
        $scenario = $this->scenarioAt();
        $this->placeUnder($scenario, 'deck-katana', 'toturi');

        $this->remove($scenario, 'deck-katana');

        self::assertSame(ConflictCard::STATE_REMOVED_FROM_GAME, $scenario->card('deck-katana')->getCurrentPlace());
    }

    public function testRejectsAHostThatIsNotInPlay(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(CannotHostCardException::class);

        $this->placeUnder($scenario, 'deck-katana', 'ambusher');
    }

    public function testRejectsTheCardItself(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(CannotHostCardException::class);

        $this->placeUnder($scenario, 'toturi', 'toturi');
    }

    public function testRejectsACardAlreadyUnderACard(): void
    {
        $scenario = $this->scenarioAt();
        $this->placeUnder($scenario, 'deck-katana', 'toturi');

        $this->expectException(NotEnabledTransitionException::class);

        $this->placeUnder($scenario, 'deck-katana', 'stronghold');
    }

    public function testTheScenarioBuildsCardsSetAside(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['uji' => '37-daidoji-uji'])
                ->underneath('uji', ['under' => '01-banzai'], faceDown: true)
                ->removedFromGame(['removed' => '01-fine-katana']))
            ->withPlayer('jane')
            ->persist();

        self::assertSame($scenario->card('uji'), $scenario->card('under')->getStack()->getHost());
        self::assertSame(ConflictCard::STATE_UNDERNEATH, $scenario->card('under')->getCurrentPlace());
        self::assertSame(ConflictCard::STATE_REMOVED_FROM_GAME, $scenario->card('removed')->getCurrentPlace());
    }
}
