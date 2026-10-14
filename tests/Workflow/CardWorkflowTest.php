<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Message\DrawConflictCards;
use App\MessageHandler\DrawConflictCardsHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\CardMarkingStore;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Card workflows: places are computed from stack and orientation, transitions move or turn the cards.
 */
class CardWorkflowTest extends AbstractKernelTestCase
{
    private function scenarioInDynastyPhase(): Scenario
    {
        return $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi')
                ->hand([
                    'banzai' => '01-banzai',
                ])
                ->conflictDeck([
                    'bottom' => '01-fine-katana',
                    'middle' => '01-banzai',
                    'top' => '01-fine-katana',
                ]))
            ->withPlayer('jane')
            ->build();
    }

    public function testTurningADynastyCardFaceupChangesItsOrientationOnly(): void
    {
        $scenario = $this->scenarioInDynastyPhase();
        $toturi = $scenario->card('toturi');

        self::service(Registry::class)->get($toturi)->apply($toturi, 'turn_faceup');

        self::assertSame(Orientation::FaceUp, $toturi->getOrientation());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $toturi->getCurrentPlace());
        self::assertSame($scenario->card('province')->getStack(), $toturi->getStack());
    }

    private function scenarioWithCardsToPlay(): Scenario
    {
        return $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp)
                ->hand([
                    'spear' => '01-master-of-the-spear',
                    'katana' => '01-fine-katana',
                ]))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay([
                'oathkeeper' => '01-vengeful-oathkeeper',
            ]))
            ->build();
    }

    public function testPlayingACharacterPutsItInANewStack(): void
    {
        $scenario = $this->scenarioWithCardsToPlay();
        $spear = $scenario->card('spear');

        self::service(Registry::class)->get($spear)->apply($spear, 'play_card');

        self::assertSame(StackType::InPlay, $spear->getStack()->getType());
        self::assertSame([$spear], $spear->getStack()->getCards()->getValues());
        self::assertSame(ConflictCard::STATE_IN_PLAY, $spear->getCurrentPlace());
        self::assertCount(1, $scenario->player('john')->getUniqueStack(StackType::Hand)->getCards());
    }

    public function testPlayingAnAttachmentPutsItInTheStackOfTheCharacter(): void
    {
        $scenario = $this->scenarioWithCardsToPlay();
        $katana = $scenario->card('katana');
        // an opponent's character
        $stack = $scenario->card('oathkeeper')->getStack();

        self::service(Registry::class)->get($katana)->apply($katana, 'play_card', [
            'stack' => $stack,
        ]);

        self::assertSame($stack, $katana->getStack());
        self::assertSame(Orientation::FaceUp, $katana->getOrientation());
        self::assertSame(ConflictCard::STATE_IN_PLAY, $katana->getCurrentPlace());
        self::assertSame($scenario->player('john'), $katana->getPlayer());
    }

    public function testPlayingAnAttachmentOnAProvinceKeepsItFaceUp(): void
    {
        $scenario = $this->scenarioWithCardsToPlay();
        $katana = $scenario->card('katana');
        $stack = $scenario->card('province')->getStack();

        self::service(Registry::class)->get($katana)->apply($katana, 'play_card', [
            'stack' => $stack,
        ]);

        self::assertSame($stack, $katana->getStack());
        self::assertSame(Orientation::FaceUp, $katana->getOrientation());
        self::assertSame(ConflictCard::STATE_IN_PLAY, $katana->getCurrentPlace());
    }

    public function testPlayingAnAttachmentRequiresATargetStack(): void
    {
        $scenario = $this->scenarioWithCardsToPlay();
        $katana = $scenario->card('katana');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('needs a target stack');

        self::service(Registry::class)->get($katana)->apply($katana, 'play_card');
    }

    public function testPlayingACharacterRejectsATargetStack(): void
    {
        $scenario = $this->scenarioWithCardsToPlay();
        $spear = $scenario->card('spear');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('enters play in a new stack');

        self::service(Registry::class)->get($spear)->apply($spear, 'play_card', [
            'stack' => $scenario->card('oathkeeper')->getStack(),
        ]);
    }

    public function testBreakingAProvinceTurnsItUpsideDown(): void
    {
        $scenario = $this->scenarioInDynastyPhase();
        $province = $scenario->card('province');
        $workflow = self::service(Registry::class)->get($province);

        $workflow->apply($province, 'reveal');
        $workflow->apply($province, 'break');

        self::assertSame(Orientation::UpsideDown, $province->getOrientation());
        self::assertSame(ProvinceCard::STATE_BROKEN, $province->getCurrentPlace());
    }

    public function testDrawingTakesTheTopCardsByPosition(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->conflictDeck([
                'bottom' => '01-fine-katana',
                'middle' => '01-banzai',
                'top' => '01-fine-katana',
            ]))
            ->withPlayer('jane')
            ->persist();
        $john = $scenario->player('john');

        // changes the positions without reordering the collection
        $john->getUniqueStack(StackType::ConflictDrawDeck)->moveCard($scenario->card('bottom'));

        self::service(DrawConflictCardsHandler::class)(new DrawConflictCards($scenario->game()->getId(), $john->getId(), $john->getId(), 2));

        self::assertSame(ConflictCard::STATE_HAND, $scenario->card('bottom')->getCurrentPlace());
        self::assertSame(ConflictCard::STATE_HAND, $scenario->card('top')->getCurrentPlace());
        self::assertSame(ConflictCard::STATE_DRAW_DECK, $scenario->card('middle')->getCurrentPlace());
    }

    public function testMarkingStoreRejectsATransitionThatDidNotMoveTheCard(): void
    {
        $scenario = $this->scenarioInDynastyPhase();

        $this->expectException(\LogicException::class);

        new CardMarkingStore()->setMarking($scenario->card('banzai'), new Marking([
            ConflictCard::STATE_IN_PLAY => 1,
        ]));
    }

    public function testBowingAndReadyingKeepsTheCardInPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('01-yojin-no-shiro', as: 'stronghold')
                ->inPlay([
                    'spear' => '01-master-of-the-spear',
                    'katana' => '01-fine-katana',
                ]))
            ->withPlayer('jane')
            ->build();
        $registry = self::service(Registry::class);

        foreach (['stronghold', 'spear', 'katana'] as $alias) {
            $card = $scenario->card($alias);
            $place = $card->getCurrentPlace();
            $workflow = $registry->get($card);

            $workflow->apply($card, 'bow');
            self::assertSame(Orientation::Sideways, $card->getOrientation());
            self::assertSame($place, $card->getCurrentPlace());

            $workflow->apply($card, 'ready');
            self::assertSame(Orientation::FaceUp, $card->getOrientation());
            self::assertSame($place, $card->getCurrentPlace());
        }
    }

    public function testDiscardingABowedCardPutsItFaceUpInTheDiscardPile(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay([
                'katana' => '01-fine-katana',
            ]))
            ->withPlayer('jane')
            ->build();
        $katana = $scenario->card('katana');
        $workflow = self::service(Registry::class)->get($katana);

        $workflow->apply($katana, 'bow');
        $workflow->apply($katana, 'discard_card');

        self::assertSame(Orientation::FaceUp, $katana->getOrientation());
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $katana->getCurrentPlace());
    }

    public function testEveryTransitionMovingACardGivesItsOrientation(): void
    {
        foreach (['conflict_card', 'dynasty_card', 'province_card', 'role_card', 'stronghold_card'] as $name) {
            $workflow = self::getContainer()->get('state_machine.' . $name);
            self::assertInstanceOf(WorkflowInterface::class, $workflow);
            foreach ($workflow->getDefinition()->getTransitions() as $transition) {
                $metadata = $workflow->getMetadataStore()->getTransitionMetadata($transition);
                if (isset($metadata['stack'])) {
                    self::assertInstanceOf(Orientation::class, $metadata['orientation'] ?? null, $name . '.' . $transition->getName());
                }
            }
        }
    }
}
