<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\EndResolution;
use App\Message\GiveActionOpportunity;
use App\Message\PassOpportunity;
use App\MessageHandler\EndResolutionHandler;
use App\MessageHandler\GiveActionOpportunityHandler;
use App\MessageHandler\PassOpportunityHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\PlayerState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The next action opportunities given to a player (Child of the Plains, Hawk Tattoo, Current of the Beryt, Austere
 * Exemplar).
 */
class GiveActionOpportunityHandlerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): PlayerBuilder|null $john
     * @param \Closure(PlayerBuilder): PlayerBuilder|null $jane
     */
    private function scenarioAt(string $place, ?\Closure $john = null, ?\Closure $jane = null): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', $john)
            ->withPlayer('jane', $jane)
            ->activePlayer('john')
            ->persist();
    }

    private function give(Scenario $scenario, string $sender, string $target, int $actions = 1): void
    {
        self::service(GiveActionOpportunityHandler::class)(new GiveActionOpportunity($scenario->game()->getId(), $scenario->player($sender)->getId(), $scenario->player($target)->getId(), $actions));
    }

    private function givenActions(Scenario $scenario, string $player): int
    {
        return $this->playerStateManager->getState($scenario->player($player))->getGivenActions();
    }

    private function isActivePlayer(Scenario $scenario, string $player): bool
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player($player));
    }

    public function testGivesTheNextActionOpportunitiesToAPlayer(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window');

        $this->give($scenario, 'jane', 'john', 3);

        self::assertSame(3, $this->givenActions($scenario, 'john'));
        self::assertTrue($this->isActivePlayer($scenario, 'john'), 'nothing changes before an opportunity is given');
        self::assertSame(['john takes the next action opportunities: 3'], $this->logLines($scenario->game()));
    }

    public function testTheActionsAddUpForTheSamePlayer(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', fn (PlayerBuilder $p): PlayerBuilder => $p->givenActions(2));

        $this->give($scenario, 'john', 'john');

        self::assertSame(3, $this->givenActions($scenario, 'john'));
    }

    public function testTheActionsReplaceThoseGivenToAnotherPlayer(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', fn (PlayerBuilder $p): PlayerBuilder => $p->givenActions(2));

        $this->give($scenario, 'john', 'jane');

        self::assertSame(0, $this->givenActions($scenario, 'john'));
        self::assertSame(1, $this->givenActions($scenario, 'jane'));
    }

    public function testThePlayerKeepsTheOpportunityAtTheEndOfTheirActionUntilTheyHaveHadThemAll(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', fn (PlayerBuilder $p): PlayerBuilder => $p->acted()->givenActions(2));
        $end = new EndResolution($scenario->game()->getId(), $scenario->player('john')->getId());

        self::service(EndResolutionHandler::class)($end);
        self::assertTrue($this->isActivePlayer($scenario, 'john'));
        self::assertSame(1, $this->givenActions($scenario, 'john'));

        $this->playerStateManager->withState($scenario->player('john'), fn (PlayerState $state): PlayerState => $state->setActed(true));
        self::service(EndResolutionHandler::class)($end);
        self::assertTrue($this->isActivePlayer($scenario, 'john'));
        self::assertSame(0, $this->givenActions($scenario, 'john'));

        $this->playerStateManager->withState($scenario->player('john'), fn (PlayerState $state): PlayerState => $state->setActed(true));
        self::service(EndResolutionHandler::class)($end);
        self::assertTrue($this->isActivePlayer($scenario, 'jane'));
    }

    public function testAnOpportunityGivenToTheOpponentGoesToThemAtTheEndOfTheAction(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', fn (PlayerBuilder $p): PlayerBuilder => $p->acted(), fn (PlayerBuilder $p): PlayerBuilder => $p->givenActions(1));

        self::service(EndResolutionHandler::class)(new EndResolution($scenario->game()->getId(), $scenario->player('john')->getId()));

        self::assertTrue($this->isActivePlayer($scenario, 'jane'));
        self::assertSame(0, $this->givenActions($scenario, 'jane'));
    }

    public function testPassingInAnActionWindowLosesTheActionsLeft(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', fn (PlayerBuilder $p): PlayerBuilder => $p->givenActions(2));

        self::service(PassOpportunityHandler::class)(new PassOpportunity($scenario->game()->getId(), $scenario->player('john')->getId()));

        self::assertSame(0, $this->givenActions($scenario, 'john'));
        self::assertTrue($this->isActivePlayer($scenario, 'jane'));
    }

    public function testPassingOutsideOfAnActionWindowKeepsTheActions(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_honor_dials')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->givenActions(1))
            ->withPlayer('jane')
            ->activePlayers('john', 'jane')
            ->persist();

        self::service(PassOpportunityHandler::class)(new PassOpportunity($scenario->game()->getId(), $scenario->player('john')->getId()));

        self::assertSame(1, $this->givenActions($scenario, 'john'));
    }

    public function testNoOpportunityIsTakenOutsideOfAnActionWindow(): void
    {
        $scenario = $this->scenarioAt('discard_from_provinces', jane: fn (PlayerBuilder $p): PlayerBuilder => $p->givenActions(1));

        self::service(PassOpportunityHandler::class)(new PassOpportunity($scenario->game()->getId(), $scenario->player('john')->getId()));

        self::assertTrue($this->isActivePlayer($scenario, 'jane'));
        self::assertSame(1, $this->givenActions($scenario, 'jane'));
    }
}
