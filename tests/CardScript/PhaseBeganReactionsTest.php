<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\PhaseBeganEvent;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\PhaseBeganCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

/**
 * The cards reacting to a phase beginning ("After the conflict phase begins", "After a phase begins").
 */
class PhaseBeganReactionsTest extends AbstractKernelTestCase
{
    /**
     * The cards of the Stronghold format reacting to a phase beginning, but the forced reactions (Steadfast Samurai,
     * Kuni Laboratory, Bayushi Shoju).
     */
    private const array CODES = [
        '01-forgotten-library', '01-secluded-temple', '06-master-of-gisei-toshi', '06-waning-hostilities',
        '07-togashi-mendicant', '08-secluded-shrine', '11-hiruma-kogoe', '13-ethereal-dreamer', '17-kakita-ryoku',
        '25-mirumoto-masashige', '26-agasha-hiyori', '28-all-out-assault', '28-expert-interpreter',
        '28-roadside-inn', '28-seize-the-day', '29-field-of-ruin', '30-daidoji-marketplace', '30-inspired-visionary',
        '33-divine-ancestry', '38-feeding-an-army', '39-reconnaissance',
    ];

    private const array ALIASES = ['library', 'kogoe', 'mendicant', 'masashige', 'ryoku', 'hiyori', 'hostilities', 'ancestry'];

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

    private function scenarioAt(string $place, string $favor): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(8)
                ->role('01-seeker-of-earth')
                ->province('01-entrenched-position', dynasty: '01-forgotten-library', dynastyAs: 'library', dynastyFaceup: true)
                ->inPlay(['kogoe' => '11-hiruma-kogoe'])
                ->inPlay(['mendicant' => '07-togashi-mendicant'])
                ->inPlay(['masashige' => '25-mirumoto-masashige'])
                ->hand(['hostilities' => '06-waning-hostilities', 'ancestry' => '33-divine-ancestry']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->inPlay(['ryoku' => '17-kakita-ryoku'])
                ->inPlay(['hiyori' => '26-agasha-hiyori'])
                ->inPlay(['initiate' => '01-togashi-initiate'])
                ->inPlay(['other_initiate' => '01-togashi-initiate']))
            ->withGameState(function (GameState $state, Scenario $scenario) use ($favor): void {
                $state->imperialFavorHolderId = $scenario->player($favor)->getId();
            })
            ->persist();
    }

    private function begin(Scenario $scenario, string $phase, string $place): void
    {
        self::service(PhaseBeganCompletedListener::class)(new CompletedEvent(
            $scenario->game(),
            new Marking(),
            new Transition($phase.'_phase_began', $phase.'_phase_begins', $place),
        ));
    }

    public function testEveryCardReactingToAPhaseBeginningSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(PhaseBeganEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testTheDrawPhase(): void
    {
        $scenario = $this->scenarioAt('honor_bid', 'jane');

        $this->begin($scenario, 'draw', 'honor_bid');

        // Hiruma Kogoe: john has lower honor; Divine Ancestry: an Earth role
        self::assertSame(['library', 'kogoe', 'ancestry'], $this->offered($scenario, 'john'));
        self::assertSame(['ryoku', 'hiyori'], $this->offered($scenario, 'jane'));
    }

    public function testTheConflictPhase(): void
    {
        $scenario = $this->scenarioAt('conflict_phase_action_window', 'john');

        $this->begin($scenario, 'conflict', 'conflict_phase_action_window');

        // Mirumoto Masashige: jane controls 4 characters, john 3
        self::assertSame(['masashige', 'hostilities', 'ancestry'], $this->offered($scenario, 'john'));
        // Kakita Ryoku: without the Imperial Favor
        self::assertSame(['hiyori'], $this->offered($scenario, 'jane'));
    }

    public function testTheFatePhase(): void
    {
        $scenario = $this->scenarioAt('discard_characters_with_no_fate', 'jane');

        $this->begin($scenario, 'fate', 'discard_characters_with_no_fate');

        self::assertSame(['mendicant', 'ancestry'], $this->offered($scenario, 'john'));
        self::assertSame(['ryoku', 'hiyori'], $this->offered($scenario, 'jane'));
    }
}
