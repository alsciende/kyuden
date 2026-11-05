<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Exception\Rules\NoConflictOpportunityException;
use App\Message\DeclareNextConflict;
use App\Message\GrantConflictOpportunity;
use App\MessageHandler\DeclareNextConflictHandler;
use App\MessageHandler\GrantConflictOpportunityHandler;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * Effects on the conflict opportunities: an additional conflict, the next conflict declared out of order.
 */
class ConflictOpportunityHandlersTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): PlayerBuilder|null $john
     */
    private function scenarioInConflictPhase(?\Closure $john = null): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', $john)
            ->withPlayer('jane')
            ->firstPlayer('john')
            ->persist();
    }

    public function testGrantsAnAdditionalConflictOfAType(): void
    {
        $scenario = $this->scenarioInConflictPhase(fn (PlayerBuilder $p): PlayerBuilder => $p->declaredConflicts(ConflictType::Military));

        self::service(GrantConflictOpportunityHandler::class)(new GrantConflictOpportunity($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->player('john')->getId(), ConflictType::Military));

        $state = self::service(PlayerStateManager::class)->getState($scenario->player('john'));
        self::assertSame(2, $state->getConflictOpportunities());
        self::assertSame([ConflictType::Military, ConflictType::Political], $state->declarableConflictTypes());
        self::assertSame(['john may declare an additional military conflict'], $this->logLines($scenario->game()));
    }

    public function testAPlayerDeclaresTheNextConflict(): void
    {
        $scenario = $this->scenarioInConflictPhase();

        self::service(DeclareNextConflictHandler::class)(new DeclareNextConflict($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->player('jane')->getId()));

        self::assertSame($scenario->player('jane')->getId(), self::service(GameStateManager::class)->getState($scenario->game())->nextConflictPlayerId);
        self::assertSame(['jane declares the next conflict'], $this->logLines($scenario->game()));
    }

    public function testRejectsDeclaringTheNextConflictWithoutAnOpportunityLeft(): void
    {
        $scenario = $this->scenarioInConflictPhase(fn (PlayerBuilder $p): PlayerBuilder => $p->conflictOpportunities(0));

        $this->expectException(NoConflictOpportunityException::class);

        self::service(DeclareNextConflictHandler::class)(new DeclareNextConflict($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->player('john')->getId()));
    }
}
