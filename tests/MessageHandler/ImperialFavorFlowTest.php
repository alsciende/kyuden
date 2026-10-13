<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Exception\Rules\NotActivePlayerException;
use App\Message\ClaimImperialFavor;
use App\Message\DeclareGloryCount;
use App\MessageHandler\ClaimImperialFavorHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\Scenario;

/**
 * The contest for the Imperial Favor (3.4) through the message bus.
 */
class ImperialFavorFlowTest extends AbstractKernelTestCase
{
    private function scenarioAtGloryCount(): Scenario
    {
        return $this->scenario()
            ->atPlace('glory_count')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->ring(Element::Air, claimedBy: 'jane')
            ->ring(Element::Fire, claimedBy: 'jane')
            ->persist();
    }

    private function declareGlory(Scenario $scenario, int $johnGlory, int $janeGlory): void
    {
        $this->messageBus->dispatch(new DeclareGloryCount($scenario->game()->getId(), $scenario->player('john')->getId(), $johnGlory));
        $this->messageBus->dispatch(new DeclareGloryCount($scenario->game()->getId(), $scenario->player('jane')->getId(), $janeGlory));
    }

    private function gameState(Scenario $scenario): GameState
    {
        return self::service(GameStateManager::class)->getState($scenario->game());
    }

    public function testTheWinnerOfTheGloryCountClaimsTheImperialFavor(): void
    {
        $scenario = $this->scenarioAtGloryCount();

        // jane: 2 + 2 claimed rings
        $this->declareGlory($scenario, 3, 2);

        self::assertSame('claim_imperial_favor', $scenario->game()->getCurrentPlace());
        self::assertSame([$scenario->player('jane')->getId()], $this->gameState($scenario)->activePlayerIds);

        $this->messageBus->dispatch(new ClaimImperialFavor($scenario->game()->getId(), $scenario->player('jane')->getId(), ConflictType::Political));

        $state = $this->gameState($scenario);
        self::assertSame($scenario->player('jane')->getId(), $state->imperialFavorHolderId);
        self::assertSame(ConflictType::Political, $state->imperialFavorSide);
        self::assertSame('fate_phase_begins', $scenario->game()->getCurrentPlace());
    }

    public function testTheImperialFavorStaysWhereItIsOnATie(): void
    {
        $scenario = $this->scenarioAtGloryCount();

        $this->declareGlory($scenario, 4, 2);

        self::assertNull($this->gameState($scenario)->imperialFavorHolderId);
        self::assertSame('fate_phase_begins', $scenario->game()->getCurrentPlace());
    }

    public function testOnlyTheWinnerOfTheGloryCountClaimsTheImperialFavor(): void
    {
        $scenario = $this->scenarioAtGloryCount();
        $this->declareGlory($scenario, 0, 0);

        $this->expectException(NotActivePlayerException::class);

        self::service(ClaimImperialFavorHandler::class)(new ClaimImperialFavor($scenario->game()->getId(), $scenario->player('john')->getId(), ConflictType::Military));
    }
}
