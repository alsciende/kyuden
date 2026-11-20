<?php

declare(strict_types=1);

namespace App\Tests\Service\CardScript;

use App\Enum\Orientation;
use App\Event\ProvinceRevealedEvent;
use App\Message\PassOpportunity;
use App\Message\PlayReactionEvent;
use App\Message\RevealProvince;
use App\Message\TriggerReactionAbility;
use App\MessageHandler\PassOpportunityHandler;
use App\MessageHandler\PlayReactionEventHandler;
use App\MessageHandler\RevealProvinceHandler;
use App\MessageHandler\TriggerReactionAbilityHandler;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class ReactionOffererTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['fury', 'seeker', 'attacker', 'at_home', 'defender', 'discarded', 'outflank', 'janes_outflank', 'ganzu'];

    private function reveal(Scenario $scenario, string $province, string $player = 'jane'): void
    {
        self::service(RevealProvinceHandler::class)(new RevealProvince(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($province)->getId(),
        ));
    }

    /**
     * @return list<ReactionWindow>
     */
    private function windows(Scenario $scenario, string $player): array
    {
        return self::service(PlayerStateManager::class)->getState($scenario->player($player))->getReactionWindows();
    }

    /**
     * @return list<list<string>> the cards offered in each window of the player, by alias (those of the scenario)
     */
    private function offeredCards(Scenario $scenario, string $player): array
    {
        $aliases = [];
        foreach (self::ALIASES as $alias) {
            try {
                $aliases[$scenario->card($alias)->getId()] = $alias;
            } catch (\OutOfBoundsException) {
            }
        }

        return array_map(
            fn (ReactionWindow $window): array => array_map(fn (string $cardId): string => $aliases[$cardId] ?? $cardId, $window->cardIds),
            $this->windows($scenario, $player),
        );
    }

    public function testTheProvinceRevealedIsOfferedToItsController(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-elemental-fury', as: 'fury'))
            ->persist();

        // whoever reveals it
        $this->reveal($scenario, 'fury', 'john');

        $windows = $this->windows($scenario, 'jane');
        self::assertCount(1, $windows);
        self::assertSame('1', $windows[0]->id);
        self::assertSame(ProvinceRevealedEvent::getTriggerName(), $windows[0]->trigger);
        self::assertSame([
            'province' => $scenario->card('fury')->getId(),
            'controller' => $scenario->player('jane')->getId(),
        ], $windows[0]->params);
        self::assertSame([['fury']], $this->offeredCards($scenario, 'jane'));
        self::assertSame([], $this->windows($scenario, 'john'));
        self::assertSame(1, self::service(GameStateManager::class)->getState($scenario->game())->reactionWindowCount);
    }

    public function testNoWindowOpensWhenNoCardReacts(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-elemental-fury', as: 'fury'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'position'))
            ->persist();

        // the facedown Elemental Fury is not revealed
        $this->reveal($scenario, 'position');

        self::assertSame([], $this->windows($scenario, 'john'));
        self::assertSame([], $this->windows($scenario, 'jane'));
        self::assertSame(0, self::service(GameStateManager::class)->getState($scenario->game())->reactionWindowCount);
    }

    public function testTheSeekerReactsToAProvinceOfItsElementItsPlayerControls(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->role('01-seeker-of-air', as: 'seeker')
                ->province('01-fertile-fields', as: 'air')
                ->province('01-entrenched-position', as: 'water')
                ->province('01-fertile-fields', as: 'air2'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-fertile-fields', as: 'janes_air'))
            ->persist();

        $this->reveal($scenario, 'water');
        $this->reveal($scenario, 'janes_air');
        self::assertSame([], $this->windows($scenario, 'john'));

        $this->reveal($scenario, 'air');
        $this->reveal($scenario, 'air2');
        // a window for each province revealed
        self::assertSame([['seeker'], ['seeker']], $this->offeredCards($scenario, 'john'));
        self::assertSame(['1', '2'], array_map(fn (ReactionWindow $window): string => $window->id, $this->windows($scenario, 'john')));
    }

    public function testACharacterReactsWhileItParticipates(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['attacker' => '34-ganzu-warrior'])
                ->inPlay(['at_home' => '34-ganzu-warrior']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'attacked')
                ->inPlay(['defender' => '34-ganzu-warrior'])
                ->dynastyDiscardPile(['discarded' => '34-ganzu-warrior']))
            ->conflict('john', 'attacked', attackers: ['attacker'], defenders: ['defender'])
            ->persist();

        $this->reveal($scenario, 'attacked');

        // not the character at home, nor the one in a discard pile (where its abilities cannot be used); the window is
        // the same for both players
        self::assertSame([['attacker']], $this->offeredCards($scenario, 'john'));
        self::assertSame([['defender']], $this->offeredCards($scenario, 'jane'));
        self::assertSame($this->windows($scenario, 'john')[0]->id, $this->windows($scenario, 'jane')[0]->id);
    }

    public function testAnEventIsOfferedFromTheHandOnly(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['outflank' => '23-outflank'])
                ->conflictDiscardPile(['discarded' => '23-outflank']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'attacked')
                ->hand(['janes_outflank' => '23-outflank']))
            ->conflict('john', 'attacked')
            ->persist();

        $this->reveal($scenario, 'attacked');

        // an opponent's province: not to jane, whose province it is
        self::assertSame([['outflank']], $this->offeredCards($scenario, 'john'));
        self::assertSame([], $this->windows($scenario, 'jane'));
    }

    public function testTriggeringACardOfferedClosesTheWindow(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['ganzu' => '34-ganzu-warrior'])
                ->hand(['outflank' => '23-outflank'])
                ->fate(5))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'attacked'))
            ->conflict('john', 'attacked', attackers: ['ganzu'])
            ->persist();
        $this->reveal($scenario, 'attacked');
        self::assertCount(1, $this->windows($scenario, 'john'));
        self::assertEqualsCanonicalizing(['ganzu', 'outflank'], $this->offeredCards($scenario, 'john')[0]);

        self::service(TriggerReactionAbilityHandler::class)(new TriggerReactionAbility(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('ganzu')->getId(),
        ));
        self::assertSame([['outflank']], $this->offeredCards($scenario, 'john'));

        self::service(PlayReactionEventHandler::class)(new PlayReactionEvent(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('outflank')->getId(),
            0,
        ));
        self::assertSame([], $this->windows($scenario, 'john'));
    }

    public function testPassingClosesTheWindowsOfThePlayer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_conflict_declaration')
            ->activePlayers('john', 'jane')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['ganzu' => '34-ganzu-warrior']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'attacked', orientation: Orientation::FaceUp)
                ->inPlay(['defender' => '34-ganzu-warrior']))
            ->conflict('john', 'attacked', attackers: ['ganzu'], defenders: ['defender'])
            ->persist();
        foreach (['john' => 'ganzu', 'jane' => 'defender'] as $player => $card) {
            self::service(PlayerStateManager::class)->withState(
                $scenario->player($player),
                fn (PlayerState $state): PlayerState => $state->openReactionWindow(new ReactionWindow('1', ProvinceRevealedEvent::getTriggerName(), [], [$scenario->card($card)->getId()])),
            );
        }

        self::service(PassOpportunityHandler::class)(new PassOpportunity($scenario->game()->getId(), $scenario->player('john')->getId()));

        self::assertSame([], $this->windows($scenario, 'john'));
        self::assertSame([['defender']], $this->offeredCards($scenario, 'jane'));
    }
}
