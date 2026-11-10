<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Exception\Rules\CannotPlayFromHandException;
use App\Exception\Rules\NotAttachedException;
use App\Exception\Rules\WrongCardTypeException;
use App\MessageHandler\BowCardHandler;
use App\MessageHandler\DetachAsCharacterHandler;
use App\MessageHandler\DiscardCardHandler;
use App\MessageHandler\MoveAttachmentHandler;
use App\MessageHandler\PlayCharacterAsAttachmentHandler;
use App\MessageHandler\TakeControlHandler;
use App\Message\BowCard;
use App\Message\DetachAsCharacter;
use App\Message\DiscardCard;
use App\Message\MoveAttachment;
use App\Message\PlayCharacterAsAttachment;
use App\Message\TakeControl;
use App\Service\ConflictService;
use App\Service\ControlService;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;

/**
 * Characters played as attachments (Tattooed Wanderer, Voice of the Ancestors), and attachments turned into characters
 * (Promising Youth, Togashi Hoshi).
 */
class CharacterAsAttachmentHandlersTest extends AbstractKernelTestCase
{
    private function scenarioAt(string $place = 'draw_phase_action_window', bool $conflict = false): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->hand(['wanderer' => '01-tattooed-wanderer', 'katana' => '01-fine-katana'])
                ->dynastyDiscardPile(['berserker' => '01-matsu-berserker'])
                ->inPlay(['toturi' => '01-akodo-toturi', 'youth' => '39-promising-youth', 'toturi-katana' => '01-fine-katana'])
                ->inPlay(['ambusher' => '01-hiruma-ambusher']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->activePlayer('john');
        if ($conflict) {
            $builder->conflict('john', 'province');
        }

        return $builder->persist();
    }

    private function play(Scenario $scenario, string $card, string $target, int $cost = 1): void
    {
        self::service(PlayCharacterAsAttachmentHandler::class)(new PlayCharacterAsAttachment(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card($card)->getId(),
            $scenario->card($target)->getId(),
            $cost,
        ));
    }

    private function detach(Scenario $scenario, string $card, string $player = 'jane'): void
    {
        self::service(DetachAsCharacterHandler::class)(new DetachAsCharacter(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testACharacterIsPlayedAsAnAttachmentOnACharacterOfAnyPlayer(): void
    {
        $scenario = $this->scenarioAt();

        $this->play($scenario, 'wanderer', 'spear');

        $wanderer = $scenario->card('wanderer');
        self::assertSame($scenario->card('spear')->getStack(), $wanderer->getStack());
        self::assertSame(ConflictCard::STATE_ATTACHED, $wanderer->getCurrentPlace());
        self::assertSame($scenario->card('spear'), $wanderer->getAttachedCharacter());
        self::assertSame($scenario->player('john'), self::service(ControlService::class)->getController($wanderer));
        self::assertNotContains($wanderer, $scenario->player('jane')->getCharactersInPlay());
        self::assertSame(4, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->hasActed());
        self::assertContains('john plays Tattooed Wanderer from john\'s hand as an attachment on Master of the Spear', $this->logLines($scenario->game()));
    }

    public function testADynastyCharacterIsPlayedAsAnAttachmentFromTheDiscardPile(): void
    {
        // Voice of the Ancestors
        $scenario = $this->scenarioAt();

        $this->play($scenario, 'berserker', 'ambusher');

        self::assertSame(DynastyCard::STATE_ATTACHED, $scenario->card('berserker')->getCurrentPlace());
        self::assertSame($scenario->card('ambusher'), $scenario->card('berserker')->getAttachedCharacter());
    }

    public function testACharacterPlayedAsAnAttachmentCannotParticipate(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(WrongCardTypeException::class);
        self::service(ConflictService::class)->assertCanParticipate($scenario->card('youth'), $scenario->player('john'));
    }

    public function testRejectsAProvince(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(WrongCardTypeException::class);
        $this->play($scenario, 'wanderer', 'province');
    }

    public function testRejectsACharacterPlayedAsAnAttachmentAsTheTarget(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(WrongCardTypeException::class);
        $this->play($scenario, 'wanderer', 'youth');
    }

    public function testRejectsACardThatIsNotACharacter(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(WrongCardTypeException::class);
        $this->play($scenario, 'katana', 'toturi');
    }

    public function testIsPlayedWhereAttachmentsArePlayed(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        $this->expectException(CannotPlayFromHandException::class);
        $this->play($scenario, 'wanderer', 'toturi');
    }

    public function testACharacterPlayedAsAnAttachmentIsBowedAndDiscardedLikeAnAttachment(): void
    {
        $scenario = $this->scenarioAt();
        $game = $scenario->game()->getId();
        $jane = $scenario->player('jane')->getId();

        self::service(BowCardHandler::class)(new BowCard($game, $jane, $scenario->card('youth')->getId()));
        self::assertSame(Orientation::Sideways, $scenario->card('youth')->getOrientation());
        self::assertSame(ConflictCard::STATE_ATTACHED, $scenario->card('youth')->getCurrentPlace());

        // the character it is attached to leaves play
        self::service(DiscardCardHandler::class)(new DiscardCard($game, $jane, $scenario->card('toturi')->getId()));
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('youth')->getCurrentPlace());
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('toturi-katana')->getCurrentPlace());
    }

    public function testFollowsTheCharacterItIsAttachedToWhoseControlIsTaken(): void
    {
        $scenario = $this->scenarioAt();

        self::service(TakeControlHandler::class)(new TakeControl(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card('toturi')->getId(),
        ));

        self::assertSame($scenario->card('toturi')->getStack(), $scenario->card('youth')->getStack());
        self::assertSame($scenario->card('toturi'), $scenario->card('youth')->getAttachedCharacter());
        self::assertSame(ConflictCard::STATE_ATTACHED, $scenario->card('youth')->getCurrentPlace());
        self::assertSame($scenario->player('john'), self::service(ControlService::class)->getController($scenario->card('youth')));
    }

    public function testACharacterPlayedAsAnAttachmentIsMovedToAnotherCharacterOnly(): void
    {
        $scenario = $this->scenarioAt();
        $move = fn (string $target) => self::service(MoveAttachmentHandler::class)(new MoveAttachment(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('youth')->getId(),
            $scenario->card($target)->getId(),
        ));

        $move('spear');
        self::assertSame($scenario->card('spear'), $scenario->card('youth')->getAttachedCharacter());
        self::assertSame(ConflictCard::STATE_ATTACHED, $scenario->card('youth')->getCurrentPlace());

        $this->expectException(WrongCardTypeException::class);
        $move('province');
    }

    public function testRejectsMovingACharacterInPlay(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(WrongCardTypeException::class);
        self::service(MoveAttachmentHandler::class)(new MoveAttachment(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('ambusher')->getId(),
            $scenario->card('spear')->getId(),
        ));
    }

    public function testACharacterPlayedAsAnAttachmentIsDetachedAndBecomesACharacter(): void
    {
        // Promising Youth, when the character it is attached to leaves play
        $scenario = $this->scenarioAt(conflict: true);
        $workflows = self::service(Registry::class);
        $youth = $scenario->card('youth');
        $workflows->get($youth)->apply($youth, 'bow');

        $this->detach($scenario, 'youth');

        self::assertSame(ConflictCard::STATE_IN_PLAY, $youth->getCurrentPlace());
        self::assertTrue($youth->isCharacterInPlay());
        self::assertSame(Orientation::FaceUp, $youth->getOrientation());
        self::assertSame(StackType::InPlay, $youth->getStack()->getType());
        self::assertSame($scenario->player('john'), $youth->getStack()->getPlayer());
        self::assertContains($youth, $scenario->player('john')->getCharactersInPlay());
        // at home
        self::assertNotContains($youth->getId(), self::service(GameStateManager::class)->getState($scenario->game())->currentConflict()->attackerIds);
        // the attachment stays on the character it was attached to
        self::assertSame($scenario->card('toturi'), $scenario->card('toturi-katana')->getAttachedCharacter());
        self::assertContains('Promising Youth is detached from Akodo Toturi and becomes a character', $this->logLines($scenario->game()));
    }

    public function testADetachedCharacterStaysUnderTheControlOfItsController(): void
    {
        $scenario = $this->scenarioAt();
        $this->play($scenario, 'wanderer', 'spear');

        $this->detach($scenario, 'wanderer');

        self::assertSame($scenario->player('john'), $scenario->card('wanderer')->getStack()->getPlayer());
        self::assertSame($scenario->player('john'), self::service(ControlService::class)->getController($scenario->card('wanderer')));
        self::assertContains($scenario->card('wanderer'), $scenario->player('john')->getCharactersInPlay());
    }

    public function testAnAttachmentIsTurnedIntoACharacter(): void
    {
        // Togashi Hoshi
        $scenario = $this->scenarioAt();

        $this->detach($scenario, 'toturi-katana', 'john');

        $katana = $scenario->card('toturi-katana');
        self::assertSame(ConflictCard::STATE_IN_PLAY, $katana->getCurrentPlace());
        self::assertTrue($katana->isCharacterInPlay());
        self::assertContains($katana, $scenario->player('john')->getCharactersInPlay());
        self::service(ConflictService::class)->assertCanParticipate($katana, $scenario->player('john'));
    }

    public function testRejectsACharacterInPlay(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(NotAttachedException::class);
        $this->detach($scenario, 'toturi');
    }

    public function testRejectsACardOutOfPlay(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(NotAttachedException::class);
        $this->detach($scenario, 'wanderer');
    }
}
