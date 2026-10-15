<?php

declare(strict_types=1);

namespace App\Tests\GameLog;

use App\Event\EventCardPlayedEvent;
use App\Event\GameEventInterface;
use App\Event\HonorBidPlacedEvent;
use App\Event\HonorLostEvent;
use App\Event\OpportunityPassedEvent;
use App\Event\StrongholdProvinceChosenEvent;
use App\GameLog\GameLog;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Psr\EventDispatcher\EventDispatcherInterface;

class GameLogEventListenerTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(string $place = 'draw_phase_action_window'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['banzai' => '01-banzai'])
                ->strongholdProvince('01-entrenched-position', as: 'province', chosen: false))
            ->withPlayer('jane')
            ->build();
    }

    private function dispatch(object $event): void
    {
        self::service(EventDispatcherInterface::class)->dispatch($event);
    }

    public function testALoggedEventWritesItsEntryWhenItIsDispatched(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->dispatch(new HonorLostEvent($scenario->game(), $scenario->player('john'), 2));

        self::assertSame(['john loses 2 honor'], $this->logLines($scenario->game()));
    }

    public function testTheEntryIsWrittenBeforeTheConsequencesOfTheEvent(): void
    {
        $scenario = $this->scenarioWithCards('play_cards_from_provinces');

        $this->dispatch(new OpportunityPassedEvent($scenario->game(), $scenario->player('john'), true));

        self::assertSame(['john passes', 'john gains 1 fate'], $this->logLines($scenario->game()));
    }

    public function testAValueVisibleToAPlayerIsHiddenFromTheOthers(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->dispatch(new HonorBidPlacedEvent($scenario->game(), $scenario->player('john'), 3));

        self::assertSame(['john bids 3 honor'], $this->logLines($scenario->game(), $scenario->player('john')));
        self::assertSame(['john bids ? honor'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testACardVisibleToAPlayerIsHiddenFromTheOthers(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->dispatch(new StrongholdProvinceChosenEvent($scenario->game(), $scenario->player('john'), $scenario->card('province')));

        self::assertSame(['john chooses Entrenched Position as their stronghold province'], $this->logLines($scenario->game(), $scenario->player('john')));
        // its id is hidden too: the provinces are not placed yet
        self::assertSame(['john chooses a hidden card as their stronghold province'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testARevealedCardIsPublicWhereverItIs(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->dispatch(new EventCardPlayedEvent($scenario->game(), $scenario->player('john'), $scenario->card('banzai')));

        self::assertSame(['john plays Banzai!'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testEveryLoggedEventShowsItsOwnProperties(): void
    {
        $logged = 0;
        foreach (glob(dirname(__DIR__, 2) . '/src/Event/*.php') ?: [] as $file) {
            $class = 'App\\Event\\' . basename($file, '.php');
            self::assertTrue(class_exists($class) || interface_exists($class), "{$file} must declare {$class}.");
            $reflection = new \ReflectionClass($class);
            $attribute = $reflection->getAttributes(GameLog::class)[0] ?? null;
            if ($attribute === null) {
                continue;
            }

            ++$logged;
            self::assertTrue($reflection->implementsInterface(GameEventInterface::class), "{$class} must implement GameEventInterface.");
            preg_match_all('/\{(\w+)\}/', $attribute->newInstance()->template, $matches);
            foreach ($matches[1] as $name) {
                self::assertTrue($reflection->hasProperty($name), "{$class} has no property {$name}.");
            }
        }

        self::assertGreaterThan(40, $logged);
    }
}
