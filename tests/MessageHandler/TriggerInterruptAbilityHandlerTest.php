<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\TriggerInterruptAbility;
use App\MessageHandler\TriggerInterruptAbilityHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

class TriggerInterruptAbilityHandlerTest extends AbstractKernelTestCase
{
    /**
     * john initiates a duel against a character of jane.
     */
    private function scenarioDuringADuel(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['banzai' => '01-banzai'])
                ->inPlay(['spear' => '01-master-of-the-spear']))
            ->activePlayer('john')
            ->duel('john', ['toturi'], ['spear'])
            ->persist();
    }

    private function trigger(Scenario $scenario, string $card, string $player = 'jane'): void
    {
        self::service(TriggerInterruptAbilityHandler::class)(new TriggerInterruptAbility(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testAPlayerWithoutTheOpportunityToActTriggersAnAbilityDuringADuel(): void
    {
        $scenario = $this->scenarioDuringADuel();
        $spear = $scenario->card('spear');

        $this->trigger($scenario, 'spear');

        self::assertSame(DynastyCard::STATE_IN_PLAY, $spear->getCurrentPlace());
        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertTrue($state->isActivePlayer($scenario->player('john')));
        self::assertFalse($state->isActivePlayer($scenario->player('jane')));
        self::assertSame(1, $this->playerStateManager->getState($scenario->player('jane'))->getResolvingInterruptsAndReactions());
    }

    public function testRejectsACardInHand(): void
    {
        $scenario = $this->scenarioDuringADuel();

        $this->expectException(NotEnabledTransitionException::class);

        $this->trigger($scenario, 'banzai');
    }

    public function testRejectsACardOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioDuringADuel();

        $this->expectException(NotOwnedCardException::class);

        $this->trigger($scenario, 'toturi');
    }
}
