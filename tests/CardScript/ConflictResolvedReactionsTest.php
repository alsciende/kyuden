<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\ConflictType;
use App\Enum\Orientation;
use App\Event\ConflictResolvedEvent;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\ReturnedHomeTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

/**
 * The cards reacting to the end of the resolution of a conflict, its participants returned home.
 */
class ConflictResolvedReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['breakthrough', 'unicorn', 'other_unicorn'];

    /**
     * @return list<string> the cards offered to the player, by alias, among those of ALIASES
     */
    private function offered(Scenario $scenario, string $player): array
    {
        $cardIds = array_merge(...array_map(
            fn (ReactionWindow $window): array => $window->cardIds,
            self::service(PlayerStateManager::class)->getState($scenario->player($player))->getReactionWindows(),
        ));

        return array_values(array_filter(self::ALIASES, fn (string $alias): bool => in_array($scenario->card($alias)->getId(), $cardIds, true)));
    }

    /**
     * @param list<ConflictType> $declared
     */
    private function scenarioReturningHome(array $declared, bool $broken): Scenario
    {
        return $this->scenario()
            ->atPlace('return_home')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->declaredConflicts(...$declared)
                ->inPlay(['unicorn' => '17-ivory-kingdoms-unicorn'])
                ->inPlay(['other_unicorn' => '17-ivory-kingdoms-unicorn'])
                ->hand(['breakthrough' => '01-breakthrough']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: $broken ? Orientation::UpsideDown : Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['unicorn'])
            ->withGameState(function (GameState $state, Scenario $scenario) use ($broken): void {
                $state->currentConflict()->brokenProvinceIds = $broken ? [$scenario->card('province')->getId()] : [];
            })
            ->persist();
    }

    public function testEveryCardReactingToTheResolutionOfAConflictSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(ConflictResolvedEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(['01-breakthrough', '17-ivory-kingdoms-unicorn'], $codes);
    }

    public function testTheFirstConflictOfThePhaseBrokeAProvince(): void
    {
        $scenario = $this->scenarioReturningHome([ConflictType::Military], broken: true);

        self::service(ReturnedHomeTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        // the other Ivory Kingdoms Unicorn was not attacking
        self::assertSame(['breakthrough', 'unicorn'], $this->offered($scenario, 'john'));
    }

    public function testTheSecondConflictOfThePhase(): void
    {
        $scenario = $this->scenarioReturningHome([ConflictType::Military, ConflictType::Political], broken: true);

        self::service(ReturnedHomeTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        self::assertSame(['unicorn'], $this->offered($scenario, 'john'));
    }

    public function testNoProvinceBroken(): void
    {
        $scenario = $this->scenarioReturningHome([ConflictType::Military], broken: false);

        self::service(ReturnedHomeTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        self::assertSame([], $this->offered($scenario, 'john'));
    }
}
