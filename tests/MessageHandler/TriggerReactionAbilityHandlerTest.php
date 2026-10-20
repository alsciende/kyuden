<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Message\TriggerReactionAbility;
use App\MessageHandler\TriggerReactionAbilityHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

class TriggerReactionAbilityHandlerTest extends AbstractKernelTestCase
{
    public function testAPlayerWithoutTheOpportunityToActTriggersAnAbility(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['gunso' => '01-akodo-gunso']))
            ->activePlayer('john')
            ->persist();
        $gunso = $scenario->card('gunso');

        self::service(TriggerReactionAbilityHandler::class)(new TriggerReactionAbility(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $gunso->getId(),
        ));

        self::assertSame(DynastyCard::STATE_IN_PLAY, $gunso->getCurrentPlace());
        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertTrue($state->isActivePlayer($scenario->player('john')));
        self::assertFalse($state->isActivePlayer($scenario->player('jane')));
        // jane ends the reaction once resolved, without using an opportunity to act
        self::assertSame(1, $this->playerStateManager->getState($scenario->player('jane'))->getResolvingInterruptsAndReactions());
        self::assertFalse($this->playerStateManager->getState($scenario->player('jane'))->hasActed());
    }
}
