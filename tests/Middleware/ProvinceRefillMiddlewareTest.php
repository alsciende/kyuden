<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Message\DiscardCard;
use App\Message\EndResolution;
use App\Message\PlayCharacterFromProvince;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The provinces scheduled by the handlers are refilled after the message that ends the last resolution.
 */
class ProvinceRefillMiddlewareTest extends AbstractKernelTestCase
{
    private function scenarioWithACharacterInAProvince(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(7)
                ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi', dynastyFaceup: true)
                ->dynastyDeck(['refill' => '01-doji-hotaru']))
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();
    }

    private function refilled(Scenario $scenario): bool
    {
        return $scenario->card('refill')->getStack() === $scenario->card('province')->getStack();
    }

    public function testTheProvinceOfACharacterPlayedIsRefilledOnceTheActionIsEnded(): void
    {
        $scenario = $this->scenarioWithACharacterInAProvince();
        $game = $scenario->game()->getId();
        $john = $scenario->player('john')->getId();

        $this->messageBus->dispatch(new PlayCharacterFromProvince($game, $john, $scenario->card('toturi')->getId(), 5, 0, 0));
        self::assertFalse($this->refilled($scenario));

        $this->messageBus->dispatch(new EndResolution($game, $john));
        self::assertTrue($this->refilled($scenario));
    }

    public function testAProvinceEmptiedOutsideOfAnyResolutionIsRefilledAfterTheMessage(): void
    {
        $scenario = $this->scenarioWithACharacterInAProvince();

        $this->messageBus->dispatch(new DiscardCard($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('toturi')->getId()));

        self::assertTrue($this->refilled($scenario));
    }
}
