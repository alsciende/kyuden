<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Enum\Orientation;
use App\Exception\Rules\CannotPlayFromHandException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInActionWindowException;
use App\Exception\Rules\NotInHandException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PlayAttachment;
use App\Message\PlayCharacterFromHand;
use App\Message\PlayEvent;
use App\MessageHandler\PlayAttachmentHandler;
use App\MessageHandler\PlayCharacterFromHandHandler;
use App\MessageHandler\PlayEventHandler;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * Characters, attachments and events played from hand.
 */
class PlayFromHandHandlersTest extends AbstractKernelTestCase
{
    private function scenarioAt(string $place, bool $conflict = false): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->hand(['ambusher' => '01-hiruma-ambusher', 'katana' => '01-fine-katana', 'banzai' => '01-banzai'])
                ->conflictDiscardPile(['discarded-ambusher' => '01-hiruma-ambusher'])
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->activePlayer('john');
        if ($conflict) {
            $builder->conflict('jane', 'province', defenders: []);
        }

        return $builder->persist();
    }

    private function playCharacter(Scenario $scenario, string $card = 'ambusher', bool $intoConflict = false): void
    {
        self::service(PlayCharacterFromHandHandler::class)(new PlayCharacterFromHand(
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

    public function testRejectsACardThatIsNotInHand(): void
    {
        $scenario = $this->scenarioAt('draw_phase_action_window');

        $this->expectException(NotInHandException::class);

        $this->playCharacter($scenario, 'discarded-ambusher');
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
    }

    public function testAnEventGoesToTheDiscardPileOnceItsCostIsPaid(): void
    {
        $scenario = $this->scenarioAt('react_to_conflict_result', conflict: true);

        self::service(PlayEventHandler::class)(new PlayEvent(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('banzai')->getId(),
            1,
        ));

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('banzai')->getCurrentPlace());
        self::assertSame(4, $this->playerStateManager->getState($scenario->player('john'))->getFate());
    }

    public function testAnEventIsPlayedByAnActivePlayer(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        $this->expectException(NotActivePlayerException::class);

        self::service(PlayEventHandler::class)(new PlayEvent(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card('banzai')->getId(),
            0,
        ));
    }
}
