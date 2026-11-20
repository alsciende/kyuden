<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\FateHolder;
use App\Event\RingFateTakenEvent;
use App\Message\DeclareConflict;
use App\Message\HonorCharacter;
use App\Message\MoveFate;
use App\MessageHandler\DeclareConflictHandler;
use App\MessageHandler\HonorCharacterHandler;
use App\MessageHandler\MoveFateHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\GameBuilder;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to fate gained from a ring (taken from the contested ring, moved from a ring by an effect) and to
 * the effect of the fire ring.
 */
class RingEffectReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['yuikimi', 'advisor', 'temples', 'tsuke'];

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

    private function scenarioAt(string $place): GameBuilder
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['yuikimi' => '20-kitsuki-yuikimi'])
                ->inPlay(['advisor' => '21-trusted-advisor', 'temples' => '25-four-temples-advisor'])
                ->inPlay(['tsuke' => '27-isawa-tsuke']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province'))
            ->activePlayer('john');
    }

    public function testEveryCardReactingToFateFromARingSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(RingFateTakenEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(['20-kitsuki-yuikimi', '21-trusted-advisor', '25-four-temples-advisor'], $codes);
    }

    public function testTheFateOfTheContestedRingTaken(): void
    {
        $scenario = $this->scenarioAt('declare_conflict')->ring(Element::Fire, fate: 2)->persist();

        self::service(DeclareConflictHandler::class)(new DeclareConflict(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            Element::Fire,
            ConflictType::Military,
            $scenario->card('province')->getId(),
            [$scenario->card('yuikimi')->getId()],
        ));

        // Trusted Advisor stays home
        self::assertSame(['yuikimi', 'temples'], $this->offered($scenario, 'john'));
    }

    public function testFateMovedFromARing(): void
    {
        $scenario = $this->scenarioAt('conflict_phase_action_window')->ring(Element::Water, fate: 1)->persist();

        self::service(MoveFateHandler::class)(new MoveFate(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            FateHolder::Ring,
            Element::Water->value,
            FateHolder::Player,
            $scenario->player('john')->getId(),
            1,
        ));

        self::assertSame(['temples'], $this->offered($scenario, 'john'));
    }

    public function testACharacterHonoredByTheEffectOfTheFireRing(): void
    {
        $scenario = $this->scenarioAt('resolve_ring_effects')
            ->conflict('john', 'province', attackers: ['yuikimi'], ring: Element::Fire)
            ->withGameState(function (GameState $state): void {
                $conflict = $state->currentConflict();
                $conflict->attackerSkill = 3;
                $conflict->defenderSkill = 0;
                $conflict->determineWinner();
            })
            ->persist();

        self::service(HonorCharacterHandler::class)(new HonorCharacter($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('advisor')->getId()));

        self::assertSame(['tsuke'], $this->offered($scenario, 'john'));
    }
}
