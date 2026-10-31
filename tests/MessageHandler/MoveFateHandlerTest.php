<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Element;
use App\Enum\FateHolder;
use App\Exception\Data\RingNotFoundException;
use App\Exception\Rules\NotEnoughFateException;
use App\Exception\Rules\NotInPlayException;
use App\Exception\Rules\SameFateHolderException;
use App\Message\MoveFate;
use App\MessageHandler\MoveFateHandler;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class MoveFateHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithFate(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(3)
                ->inPlay(['toturi' => '01-akodo-toturi'], fate: 2)
                ->hand(['adept' => '01-adept-of-shadows']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(1)
                ->inPlay(['spear' => '01-master-of-the-spear']))
            ->ring(Element::Fire, fate: 2)
            ->persist();
    }

    private function move(Scenario $scenario, FateHolder $source, string $sourceId, FateHolder $target, string $targetId, int $amount = 1): void
    {
        self::service(MoveFateHandler::class)(new MoveFate(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $source,
            $sourceId,
            $target,
            $targetId,
            $amount,
        ));
    }

    private function playerFate(Scenario $scenario, string $player): int
    {
        return $this->playerStateManager->getState($scenario->player($player))->getFate();
    }

    private function cardFate(Scenario $scenario, string $card): int
    {
        return self::service(CardStateManager::class)->getState($scenario->card($card))->getFate();
    }

    private function ringFate(Scenario $scenario, Element $ring): int
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->getRing($ring)->fate;
    }

    public function testMovesFateFromAFatePoolToARing(): void
    {
        $scenario = $this->scenarioWithFate();

        $this->move($scenario, FateHolder::Player, $scenario->player('john')->getId(), FateHolder::Ring, Element::Void->value);

        self::assertSame(2, $this->playerFate($scenario, 'john'));
        self::assertSame(1, $this->ringFate($scenario, Element::Void));
        self::assertSame(['1 fate moves from john to the void ring'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testMovesFateFromARingToAFatePool(): void
    {
        $scenario = $this->scenarioWithFate();

        $this->move($scenario, FateHolder::Ring, Element::Fire->value, FateHolder::Player, $scenario->player('john')->getId(), 2);

        self::assertSame(0, $this->ringFate($scenario, Element::Fire));
        self::assertSame(5, $this->playerFate($scenario, 'john'));
    }

    public function testMovesFateFromACharacterToAnother(): void
    {
        $scenario = $this->scenarioWithFate();

        $this->move($scenario, FateHolder::Card, $scenario->card('toturi')->getId(), FateHolder::Card, $scenario->card('spear')->getId(), 2);

        self::assertSame(0, $this->cardFate($scenario, 'toturi'));
        self::assertSame(2, $this->cardFate($scenario, 'spear'));
    }

    public function testMovesFateFromACharacterToTheFatePoolOfItsOwner(): void
    {
        $scenario = $this->scenarioWithFate();

        $this->move($scenario, FateHolder::Card, $scenario->card('toturi')->getId(), FateHolder::Player, $scenario->player('john')->getId());

        self::assertSame(1, $this->cardFate($scenario, 'toturi'));
        self::assertSame(4, $this->playerFate($scenario, 'john'));
    }

    public function testMovesFateFromAFatePoolToAnother(): void
    {
        $scenario = $this->scenarioWithFate();

        $this->move($scenario, FateHolder::Player, $scenario->player('john')->getId(), FateHolder::Player, $scenario->player('jane')->getId());

        self::assertSame(2, $this->playerFate($scenario, 'john'));
        self::assertSame(2, $this->playerFate($scenario, 'jane'));
    }

    public function testRejectsMoreFateThanTheSourceHolds(): void
    {
        $scenario = $this->scenarioWithFate();

        $this->expectException(NotEnoughFateException::class);

        $this->move($scenario, FateHolder::Ring, Element::Fire->value, FateHolder::Player, $scenario->player('john')->getId(), 3);
    }

    public function testRejectsACardNotInPlayAsTarget(): void
    {
        $scenario = $this->scenarioWithFate();

        $this->expectException(NotInPlayException::class);

        $this->move($scenario, FateHolder::Player, $scenario->player('john')->getId(), FateHolder::Card, $scenario->card('adept')->getId());
    }

    public function testRejectsTheSameHolder(): void
    {
        $scenario = $this->scenarioWithFate();

        $this->expectException(SameFateHolderException::class);

        $this->move($scenario, FateHolder::Ring, Element::Fire->value, FateHolder::Ring, Element::Fire->value);
    }

    public function testRejectsAnUnknownRing(): void
    {
        $scenario = $this->scenarioWithFate();

        $this->expectException(RingNotFoundException::class);

        $this->move($scenario, FateHolder::Ring, 'wind', FateHolder::Player, $scenario->player('john')->getId());
    }
}
