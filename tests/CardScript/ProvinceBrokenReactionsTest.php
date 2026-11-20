<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\Orientation;
use App\Event\ProvinceBrokenEvent;
use App\Message\BreakProvince;
use App\MessageHandler\BreakProvinceHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\ProvincesBrokenTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

/**
 * The cards reacting to a province broken ("After you break a province during a conflict…"), broken by the attacking
 * player at the resolution of a conflict or by an effect.
 */
class ProvinceBrokenReactionsTest extends AbstractKernelTestCase
{
    private const array CODES = [
        '01-for-greater-glory', '01-shinjo-altansarnai', '16-glorious-victory', '16-utaku-kamoko',
        '21-callous-ashigaru', '40-battle-meditation', '40-castle-of-the-forgotten', '40-overrun',
        '40-repentant-legion',
    ];

    /**
     * @param list<string> $aliases
     *
     * @return list<string> the cards offered to the player, by alias, among those given
     */
    private function offered(Scenario $scenario, string $player, array $aliases): array
    {
        $cardIds = array_merge(...array_map(
            fn (ReactionWindow $window): array => $window->cardIds,
            self::service(PlayerStateManager::class)->getState($scenario->player($player))->getReactionWindows(),
        ));

        return array_values(array_filter($aliases, fn (string $alias): bool => in_array($scenario->card($alias)->getId(), $cardIds, true)));
    }

    public function testEveryCardReactingToAProvinceBrokenSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(ProvinceBrokenEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testTheAttackingPlayerBreaksTheProvinceOfAConflict(): void
    {
        $scenario = $this->scenario()
            ->atPlace('break_province')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['altansarnai' => '01-shinjo-altansarnai'])
                ->inPlay(['legion' => '40-repentant-legion'])
                ->hand(['glory' => '01-for-greater-glory', 'victory' => '16-glorious-victory']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kamoko' => '16-utaku-kamoko'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['altansarnai'])
            ->withGameState(function (GameState $state, Scenario $scenario): void {
                $conflict = $state->currentConflict();
                $conflict->attackerSkill = 8;
                $conflict->defenderSkill = 0;
                $conflict->provinceStrengths[$scenario->card('province')->getId()] = 3;
                $conflict->determineWinner();
            })
            ->persist();
        $aliases = ['altansarnai', 'legion', 'glory', 'victory', 'kamoko'];

        self::service(ProvincesBrokenTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        // Repentant Legion does not participate
        self::assertSame(['altansarnai', 'glory', 'victory'], $this->offered($scenario, 'john', $aliases));
        self::assertSame(['kamoko'], $this->offered($scenario, 'jane', $aliases));
    }

    public function testThePlayerWhoseEffectBreaksAProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kamoko' => '16-utaku-kamoko'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('40-castle-of-the-forgotten', as: 'castle')
                ->hand(['glory' => '01-for-greater-glory']))
            ->persist();
        $aliases = ['kamoko', 'castle', 'glory'];

        self::service(BreakProvinceHandler::class)(new BreakProvince(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card('province')->getId(),
        ));

        self::assertSame(['kamoko'], $this->offered($scenario, 'john', $aliases));
        // not For Greater Glory, outside of a conflict
        self::assertSame(['castle'], $this->offered($scenario, 'jane', $aliases));
    }
}
