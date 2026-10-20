<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Exception\Rules\NotInHandException;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\PlayInterruptEvent;
use App\MessageHandler\PlayInterruptEventHandler;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class PlayInterruptEventHandlerTest extends AbstractKernelTestCase
{
    /**
     * john initiates a duel against a character of jane, who has the event in hand.
     */
    private function scenarioDuringADuel(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['banzai' => '01-banzai'])
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(3)
                ->hand(['stay-your-hand' => '16-stay-your-hand'])
                ->conflictDiscardPile(['discarded' => '16-stay-your-hand'])
                ->inPlay(['spear' => '01-master-of-the-spear']))
            ->activePlayer('john')
            ->duel('john', ['toturi'], ['spear'])
            ->persist();
    }

    private function play(Scenario $scenario, string $card = 'stay-your-hand', string $player = 'jane'): void
    {
        self::service(PlayInterruptEventHandler::class)(new PlayInterruptEvent(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
            1,
        ));
    }

    public function testAPlayerWithoutTheOpportunityToActPlaysAnEventDuringADuel(): void
    {
        $scenario = $this->scenarioDuringADuel();

        $this->play($scenario);

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('stay-your-hand')->getCurrentPlace());
        self::assertSame(2, self::service(PlayerStateManager::class)->getState($scenario->player('jane'))->getFate());
        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertTrue($state->isActivePlayer($scenario->player('john')));
        self::assertFalse($state->isActivePlayer($scenario->player('jane')));
        self::assertSame(1, self::service(PlayerStateManager::class)->getState($scenario->player('jane'))->getResolvingInterruptsAndReactions());
    }

    public function testRejectsAnEventOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioDuringADuel();

        $this->expectException(NotOwnedCardException::class);

        $this->play($scenario, card: 'banzai');
    }

    public function testRejectsAnEventThatIsNotInHand(): void
    {
        $scenario = $this->scenarioDuringADuel();

        $this->expectException(NotInHandException::class);

        $this->play($scenario, card: 'discarded');
    }
}
