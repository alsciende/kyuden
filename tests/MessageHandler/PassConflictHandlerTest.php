<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Exception\Rules\NoConflictOpportunityException;
use App\Exception\Rules\NotActivePlayerException;
use App\Message\PassConflict;
use App\MessageHandler\PassConflictHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class PassConflictHandlerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): PlayerBuilder|null $john
     */
    private function scenarioAtDeclareConflict(?\Closure $john = null): Scenario
    {
        return $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john', $john)
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();
    }

    private function pass(Scenario $scenario, string $player): PassConflict
    {
        return new PassConflict($scenario->game()->getId(), $scenario->player($player)->getId());
    }

    public function testPassingUsesAConflictOpportunityOfNoType(): void
    {
        $scenario = $this->scenarioAtDeclareConflict(fn (PlayerBuilder $p): PlayerBuilder => $p->declaredConflicts(ConflictType::Military));

        self::service(PassConflictHandler::class)($this->pass($scenario, 'john'));

        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(0, $state->getConflictOpportunities());
        self::assertSame([ConflictType::Military], $state->getDeclaredConflictTypes());
        self::assertSame($scenario->player('john')->getId(), self::service(GameStateManager::class)->getState($scenario->game())->lastConflictPlayerId);
    }

    public function testTheActionWindowOfTheConflictPhaseOpensAgain(): void
    {
        $scenario = $this->scenarioAtDeclareConflict();

        // through the bus, so that automatic transitions happen
        $this->messageBus->dispatch($this->pass($scenario, 'john'));

        self::assertSame('conflict_phase_action_window', $scenario->game()->getCurrentPlace());
    }

    public function testRejectsAPlayerWithNoConflictOpportunityLeft(): void
    {
        $scenario = $this->scenarioAtDeclareConflict(fn (PlayerBuilder $p): PlayerBuilder => $p->conflictOpportunities(0));

        $this->expectException(NoConflictOpportunityException::class);

        self::service(PassConflictHandler::class)($this->pass($scenario, 'john'));
    }

    public function testRejectsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->scenarioAtDeclareConflict();

        $this->expectException(NotActivePlayerException::class);

        self::service(PassConflictHandler::class)($this->pass($scenario, 'jane'));
    }
}
