<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Enum\StackType;
use App\Exception\Rules\AlreadyControlledException;
use App\Exception\Rules\NotControlledCardException;
use App\Message\DiscardCard;
use App\Message\MoveToConflict;
use App\Message\TakeControl;
use App\Message\TriggerActionAbility;
use App\MessageHandler\DiscardCardHandler;
use App\MessageHandler\MoveToConflictHandler;
use App\MessageHandler\TakeControlHandler;
use App\MessageHandler\TriggerActionAbilityHandler;
use App\Service\ControlService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\GameBuilder;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\TransitionException;

/**
 * Taking control of a card in play ("Control and Ownership" in the glossary): Blackmail, Calling in Favors.
 */
class TakeControlHandlerTest extends AbstractKernelTestCase
{
    private function builder(string $place = 'draw_phase_action_window'): GameBuilder
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'], bowed: true, fate: 1, personalHonor: PersonalHonor::Honored)
                ->inPlay(['scout' => '01-eager-scout'])
                ->hand(['fan' => '01-ornate-fan'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->inPlay(['spear' => '01-master-of-the-spear', 'jane-katana' => '01-fine-katana']));
    }

    private function takeControl(Scenario $scenario, string $card, string $player = 'jane'): void
    {
        self::service(TakeControlHandler::class)(new TakeControl(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    private function controller(Scenario $scenario, string $card): string
    {
        return self::service(ControlService::class)->getController($scenario->card($card))->getId();
    }

    public function testACharacterJoinsThePlayAreaOfItsNewControllerWithItsAttachments(): void
    {
        $scenario = $this->builder()->persist();
        $toturi = $scenario->card('toturi');

        $this->takeControl($scenario, 'toturi');

        $jane = $scenario->player('jane');
        self::assertSame($jane->getId(), $this->controller($scenario, 'toturi'));
        self::assertSame($jane, $toturi->getStack()->getPlayer());
        self::assertSame(StackType::InPlay, $toturi->getStack()->getType());
        self::assertSame([$toturi, $scenario->card('katana')], $toturi->getStack()->getOrderedCards());
        // the attachments do not change control
        self::assertSame($scenario->player('john')->getId(), $this->controller($scenario, 'katana'));
        // the character keeps its state and its owner
        self::assertSame(Orientation::Sideways, $toturi->getOrientation());
        self::assertSame(1, self::service(CardStateManager::class)->getState($toturi)->getFate());
        self::assertSame($scenario->player('john'), $toturi->getPlayer());
        self::assertContains($toturi, $jane->getCharactersInPlay());
        self::assertNotContains($toturi, $scenario->player('john')->getCharactersInPlay());
        self::assertSame(['jane takes control of Akodo Toturi'], $this->logLines($scenario->game()));
    }

    public function testAnAttachmentStaysWhereItIs(): void
    {
        $scenario = $this->builder()->persist();
        $stack = $scenario->card('jane-katana')->getStack();

        $this->takeControl($scenario, 'jane-katana', player: 'john');

        self::assertSame($scenario->player('john')->getId(), $this->controller($scenario, 'jane-katana'));
        self::assertSame($stack, $scenario->card('jane-katana')->getStack());
        self::assertSame($scenario->player('jane')->getId(), $this->controller($scenario, 'spear'));
    }

    public function testAParticipatingCharacterParticipatesOnTheSideOfItsNewController(): void
    {
        $scenario = $this->builder('conflict_action_window')
            ->conflict('jane', 'province', attackers: ['spear'], defenders: ['scout'])
            ->persist();

        $this->takeControl($scenario, 'scout');

        $conflict = self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
        self::assertSame([$scenario->card('spear')->getId(), $scenario->card('scout')->getId()], $conflict->attackerIds);
        self::assertSame([], $conflict->defenderIds);
    }

    public function testACharacterMovedToTheConflictJoinsTheSideOfItsController(): void
    {
        $scenario = $this->builder('conflict_action_window')
            ->conflict('jane', 'province', attackers: ['spear'])
            ->persist();
        $this->takeControl($scenario, 'scout');

        self::service(MoveToConflictHandler::class)(new MoveToConflict($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('scout')->getId()));

        $conflict = self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
        self::assertContains($scenario->card('scout')->getId(), $conflict->attackerIds);
        self::assertSame([], $conflict->defenderIds);
    }

    public function testItsOwnerTakesItBack(): void
    {
        $scenario = $this->builder()->persist();
        $this->takeControl($scenario, 'toturi');

        $this->takeControl($scenario, 'toturi', player: 'john');

        self::assertSame($scenario->player('john')->getId(), $this->controller($scenario, 'toturi'));
        self::assertNull(self::service(CardStateManager::class)->getState($scenario->card('toturi'))->getControllerId());
        self::assertSame($scenario->player('john'), $scenario->card('toturi')->getStack()->getPlayer());
    }

    public function testACardLeavingPlayGoesToItsOwnerAndItsControllerGetsTheHonor(): void
    {
        $scenario = $this->builder()->persist();
        $this->takeControl($scenario, 'toturi');

        self::service(DiscardCardHandler::class)(new DiscardCard($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('toturi')->getId()));

        $toturi = $scenario->card('toturi');
        self::assertSame($scenario->player('john')->getUniqueStack(StackType::DynastyDiscardPile), $toturi->getStack());
        self::assertNull(self::service(CardStateManager::class)->getState($toturi)->getControllerId());
        // the honored character: its controller gains 1 honor
        self::assertSame(11, self::service(PlayerStateManager::class)->getState($scenario->player('jane'))->getHonor());
        self::assertSame(10, self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getHonor());
        // its attachment, still controlled by john, is discarded to john's discard pile
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('katana')->getCurrentPlace());
        self::assertNull(self::service(CardStateManager::class)->getState($scenario->card('katana'))->getControllerId());
    }

    public function testTheAbilitiesOfACardAreTriggeredByItsController(): void
    {
        $scenario = $this->builder('play_cards_from_provinces')->activePlayer('john')->persist();
        $this->takeControl($scenario, 'scout');

        $this->expectException(NotControlledCardException::class);

        self::service(TriggerActionAbilityHandler::class)(new TriggerActionAbility($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('scout')->getId()));
    }

    public function testRejectsACardThePlayerControls(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(AlreadyControlledException::class);

        $this->takeControl($scenario, 'toturi', player: 'john');
    }

    public function testRejectsACardThatIsNotInPlay(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(TransitionException::class);

        $this->takeControl($scenario, 'fan');
    }

    public function testRejectsAProvince(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(TransitionException::class);

        $this->takeControl($scenario, 'province');
    }
}
