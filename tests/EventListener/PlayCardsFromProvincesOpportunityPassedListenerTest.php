<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Event\OpportunityPassedEvent;
use App\EventListener\PlayCardsFromProvincesOpportunityPassedListener;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class PlayCardsFromProvincesOpportunityPassedListenerTest extends AbstractKernelTestCase
{
    private function passed(string $place, bool $firstToPass): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(2))
            ->withPlayer('jane')
            ->build();

        self::service(PlayCardsFromProvincesOpportunityPassedListener::class)(
            new OpportunityPassedEvent($scenario->game(), $scenario->player('john'), $firstToPass),
        );

        return $scenario;
    }

    public function testTheFirstPlayerToPassGainsOneFate(): void
    {
        $scenario = $this->passed('play_cards_from_provinces', true);

        self::assertSame(3, $this->playerStateManager->getState($scenario->player('john'))->getFate());
    }

    public function testTheOtherPlayersToPassGainNoFate(): void
    {
        $scenario = $this->passed('play_cards_from_provinces', false);

        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getFate());
    }

    public function testNoFateIsGainedInOtherSteps(): void
    {
        $scenario = $this->passed('reveal_honor_dials', true);

        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getFate());
    }
}
