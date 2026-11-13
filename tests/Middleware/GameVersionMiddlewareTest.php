<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Exception\Api\StaleVersionException;
use App\Entity\CardTypes\DynastyCard;
use App\Message\LoseHonor;
use App\Message\RevealDynastyCard;
use App\Middleware\Stamp\ExpectedVersionStamp;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class GameVersionMiddlewareTest extends AbstractKernelTestCase
{
    private function scenarioWithJohn(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->persist();
    }

    private function loseHonor(Scenario $scenario): LoseHonor
    {
        return new LoseHonor($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('john')->getId(), 1);
    }

    public function testAcceptsAMessageSentForTheCurrentVersion(): void
    {
        $scenario = $this->scenarioWithJohn();

        $this->messageBus->dispatch($this->loseHonor($scenario), [new ExpectedVersionStamp(0)]);

        self::assertSame(9, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
        self::assertSame(1, $scenario->game()->getVersion());
    }

    public function testRejectsAMessageSentForAPreviousVersion(): void
    {
        $scenario = $this->scenarioWithJohn();
        $this->messageBus->dispatch($this->loseHonor($scenario));

        try {
            $this->messageBus->dispatch($this->loseHonor($scenario), [new ExpectedVersionStamp(0)]);
            self::fail('The message should have been rejected.');
        } catch (StaleVersionException) {
        }

        self::assertSame(9, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
        self::assertSame(1, $scenario->game()->getVersion());
    }

    public function testPlayersRevealTheirDynastyCardsAtOnce(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '01-akodo-toturi', dynastyAs: 'toturi')
                ->province('01-night-raid', dynasty: '01-matsu-berserker', dynastyAs: 'berserker'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-fertile-fields', dynasty: '01-doji-challenger', dynastyAs: 'challenger'))
            ->persist();
        $gameId = $scenario->game()->getId();

        // both players have read the version 0 of the game, and the opponent of each reveals a card meanwhile
        foreach ([['john', 'toturi'], ['jane', 'challenger'], ['john', 'berserker']] as [$player, $card]) {
            $this->messageBus->dispatch(
                new RevealDynastyCard($gameId, $scenario->player($player)->getId(), $scenario->card($card)->getId()),
                [new ExpectedVersionStamp(0)],
            );
        }

        foreach (['toturi', 'challenger', 'berserker'] as $card) {
            self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card($card)->getCurrentPlace());
        }
    }
}
