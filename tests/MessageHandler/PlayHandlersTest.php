<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Enum\AbilityTiming;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Exception\Rules\CannotPlayFromHandException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInActionWindowException;
use App\Exception\Rules\NotPlayableException;
use App\Exception\Rules\WrongAbilityTimingException;
use App\Exception\Rules\WrongCardTypeException;
use App\MessageHandler\PlayActionEventHandler;
use App\MessageHandler\PlayAttachmentHandler;
use App\MessageHandler\PlayCharacterHandler;
use App\Message\PlayActionEvent;
use App\Message\PlayAttachment;
use App\Message\PlayCharacter;
use App\Service\ControlService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Model\Resolution;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * Characters, attachments and events played during an action window, from hand or from elsewhere.
 */
class PlayHandlersTest extends AbstractKernelTestCase
{
    private function scenarioAt(string $place, bool $conflict = false): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->hand(['ambusher' => '01-hiruma-ambusher', 'katana' => '01-fine-katana', 'banzai' => '01-banzai', 'stay-your-hand' => '16-stay-your-hand'])
                ->conflictDiscardPile(['discarded-ambusher' => '01-hiruma-ambusher'])
                ->conflictDeck(['deck-banzai' => '01-banzai', 'top-katana' => '01-fine-katana'])
                ->dynastyDeck(['deck-gunso' => '01-akodo-gunso'])
                ->dynastyDiscardPile(['discarded-whisperer' => '01-doji-whisperer'])
                ->province('01-the-art-of-peace', as: 'own-province', dynasty: '01-matsu-berserker', dynastyAs: 'berserker', dynastyFaceup: true)
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->conflictDiscardPile(['jane-katana' => '01-fine-katana', 'jane-banzai' => '01-banzai'])
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->activePlayer('john');
        if ($conflict) {
            $builder->conflict('john', 'province');
        }

        return $builder->persist();
    }

    private function playCharacter(Scenario $scenario, string $card = 'ambusher', bool $intoConflict = false): void
    {
        self::service(PlayCharacterHandler::class)(new PlayCharacter(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card($card)->getId(),
            2,
            1,
            1,
            $intoConflict,
        ));
    }

    public function testACharacterIsPlayedAtHome(): void
    {
        $scenario = $this->scenarioAt('draw_phase_action_window');

        $this->playCharacter($scenario);

        self::assertSame(ConflictCard::STATE_IN_PLAY, $scenario->card('ambusher')->getCurrentPlace());
        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        self::assertSame(1, self::service(CardStateManager::class)->getState($scenario->card('ambusher'))->getFate());
        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->hasActed());
        // a character played is not a resolution: nothing tells the players which effect to apply
        self::assertSame([], $this->playerStateManager->getState($scenario->player('john'))->getResolutions());
    }

    public function testACharacterIsPlayedIntoTheConflictOnItsControllersSide(): void
    {
        // jane attacks a province of john, who plays a character into the conflict as a defender
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->hand(['ambusher' => '01-hiruma-ambusher'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['spear' => '01-master-of-the-spear']))
            ->conflict('jane', 'province', attackers: ['spear'])
            ->activePlayer('john')
            ->persist();

        $this->playCharacter($scenario, intoConflict: true);

        $conflict = self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
        self::assertSame([$scenario->card('ambusher')->getId()], $conflict->defenderIds);
    }

    public function testCharactersCannotBePlayedFromHandDuringTheSpecialActionWindowOfTheDynastyPhase(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        $this->expectException(CannotPlayFromHandException::class);

        $this->playCharacter($scenario);
    }

    public function testRejectsAStepThatIsNotAnActionWindow(): void
    {
        $scenario = $this->scenarioAt('declare_conflict');

        $this->expectException(NotInActionWindowException::class);

        $this->playCharacter($scenario);
    }

    public function testACharacterIsPlayedFromADiscardPile(): void
    {
        $scenario = $this->scenarioAt('draw_phase_action_window');

        $this->playCharacter($scenario, 'discarded-ambusher');

        self::assertSame(ConflictCard::STATE_IN_PLAY, $scenario->card('discarded-ambusher')->getCurrentPlace());
        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getFate());
    }

    public function testADynastyCharacterIsPlayedFromAProvinceDuringAConflict(): void
    {
        // Prepared Ambush, Wind's Path: the province is refilled once the action is ended
        $scenario = $this->scenarioAt('conflict_action_window', conflict: true);

        $this->playCharacter($scenario, 'berserker', intoConflict: true);

        self::assertSame(DynastyCard::STATE_IN_PLAY, $scenario->card('berserker')->getCurrentPlace());
        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([$scenario->card('berserker')->getId()], $state->currentConflict()->attackerIds);
        self::assertSame([$scenario->card('own-province')->getStack()->getId()], $state->provincesToRefill);
    }

    public function testADynastyCharacterIsPlayedFromADeck(): void
    {
        // Third Whisker Warrens
        $scenario = $this->scenarioAt('draw_phase_action_window');

        $this->playCharacter($scenario, 'deck-gunso');

        self::assertSame(DynastyCard::STATE_IN_PLAY, $scenario->card('deck-gunso')->getCurrentPlace());
    }

    public function testADynastyCharacterIsPlayedFromADiscardPile(): void
    {
        // Gateway to Meido
        $scenario = $this->scenarioAt('draw_phase_action_window');

        $this->playCharacter($scenario, 'discarded-whisperer');

        self::assertSame(DynastyCard::STATE_IN_PLAY, $scenario->card('discarded-whisperer')->getCurrentPlace());
    }

    public function testRejectsACharacterInPlay(): void
    {
        $scenario = $this->scenarioAt('draw_phase_action_window');

        $this->expectException(NotPlayableException::class);

        $this->playCharacter($scenario, 'toturi');
    }

    public function testRejectsACardOfAnotherType(): void
    {
        $scenario = $this->scenarioAt('draw_phase_action_window');

        $this->expectException(WrongCardTypeException::class);

        $this->playCharacter($scenario, 'katana');
    }

    public function testAnAttachmentIsAttachedToACharacterOfAnyPlayer(): void
    {
        $scenario = $this->scenarioAt('draw_phase_action_window');

        self::service(PlayAttachmentHandler::class)(new PlayAttachment(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('katana')->getId(),
            $scenario->card('spear')->getId(),
            0,
        ));

        self::assertSame($scenario->card('spear')->getStack(), $scenario->card('katana')->getStack());
        self::assertSame(ConflictCard::STATE_IN_PLAY, $scenario->card('katana')->getCurrentPlace());
        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->hasActed());
    }

    public function testAnAttachmentOfTheOpponentIsPlayedUnderTheControlOfThePlayer(): void
    {
        // Kunshu: an attachment of the conflict discard pile of the opponent
        $scenario = $this->scenarioAt('draw_phase_action_window');

        self::service(PlayAttachmentHandler::class)(new PlayAttachment(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('jane-katana')->getId(),
            $scenario->card('toturi')->getId(),
            0,
        ));

        self::assertSame($scenario->card('toturi')->getStack(), $scenario->card('jane-katana')->getStack());
        self::assertSame($scenario->player('jane'), $scenario->card('jane-katana')->getPlayer());
        self::assertSame($scenario->player('john'), self::service(ControlService::class)->getController($scenario->card('jane-katana')));
    }

    public function testAnAttachmentIsPlayedFromADeck(): void
    {
        // Pillow Book, Artisan Academy: the top card of the deck
        $scenario = $this->scenarioAt('draw_phase_action_window');

        self::service(PlayAttachmentHandler::class)(new PlayAttachment(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('top-katana')->getId(),
            $scenario->card('toturi')->getId(),
            0,
        ));

        self::assertSame($scenario->card('toturi')->getStack(), $scenario->card('top-katana')->getStack());
    }

    private function playEvent(Scenario $scenario, string $player = 'john', string $card = 'banzai'): void
    {
        self::service(PlayActionEventHandler::class)(new PlayActionEvent(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
            1,
        ));
    }

    public function testAnEventGoesToTheDiscardPileOnceItsCostIsPaid(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', conflict: true);

        $this->playEvent($scenario);

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('banzai')->getCurrentPlace());
        self::assertSame(4, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->hasActed());
        self::assertEquals([new Resolution($scenario->card('banzai')->getId(), AbilityTiming::Action)], $this->playerStateManager->getState($scenario->player('john'))->getResolutions());
    }

    public function testAnEventIsPlayedFromADeck(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', conflict: true);

        $this->playEvent($scenario, card: 'deck-banzai');

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('deck-banzai')->getCurrentPlace());
        self::assertSame($scenario->player('john')->getUniqueStack(StackType::ConflictDiscardPile), $scenario->card('deck-banzai')->getStack());
    }

    public function testAnEventOfTheOpponentGoesToTheDiscardPileOfItsOwner(): void
    {
        // Kunshu, Bayushi Kachiko: from the conflict discard pile of the opponent, back on top of it
        $scenario = $this->scenarioAt('conflict_action_window', conflict: true);

        $this->playEvent($scenario, card: 'jane-banzai');

        $discardPile = $scenario->player('jane')->getUniqueStack(StackType::ConflictDiscardPile);
        self::assertSame($discardPile, $scenario->card('jane-banzai')->getStack());
        self::assertSame($scenario->card('jane-banzai'), $discardPile->getTopCards(1)[0]);
        self::assertSame(4, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->hasActed());
    }

    public function testAnEventIsPlayedByAnActivePlayer(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        $this->expectException(NotActivePlayerException::class);

        $this->playEvent($scenario, player: 'jane');
    }

    public function testAnEventIsPlayedInAnActionWindowOnly(): void
    {
        $scenario = $this->scenarioAt('react_to_conflict_result', conflict: true);

        $this->expectException(NotInActionWindowException::class);

        $this->playEvent($scenario);
    }

    public function testAnEventIsPlayedWithTheMessageOfItsTiming(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', conflict: true);

        $this->expectException(WrongAbilityTimingException::class);

        $this->playEvent($scenario, card: 'stay-your-hand');
    }
}
