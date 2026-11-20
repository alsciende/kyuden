<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\ProvinceRevealedEvent;
use App\Message\RevealProvince;
use App\MessageHandler\RevealProvinceHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to a province being revealed ("After a province is revealed", "After this province is revealed"):
 * the conditions of their scripts, beside those of ReactionOffererTest.
 */
class ProvinceRevealedReactionsTest extends AbstractKernelTestCase
{
    /**
     * The 34 cards of the Stronghold format (not Strategizing, of the Enlightenment format).
     */
    private const array CODES = [
        '01-elemental-fury', '01-night-raid', '01-rally-to-the-cause', '02-tears-of-amaterasu',
        '05-guardians-of-the-seikitsu', '12-flooded-waste', '13-raging-battleground', '16-illustrious-forge',
        '16-temple-of-the-dragons', '17-khan-s-ordu', '18-market-of-kaze-no-kami', '19-scene-of-the-crime',
        '22-emperor-s-summons', '29-sharing-shinsei-s-wisdom', '30-retire-to-the-brotherhood', '30-spectral-visitation',
        '32-breaking-in', '36-brushfires', '38-seeking-enlightenment', '40-deserted-shrine', '40-offerings-to-the-kami',
        '01-seeker-of-air', '01-seeker-of-earth', '01-seeker-of-fire', '01-seeker-of-void', '01-seeker-of-water',
        '31-kitsuki-chiari', '33-waterfall-tattoo',
        '28-way-station-trader', '34-ganzu-warrior', '04-child-of-the-plains', '17-shinjo-trailblazer', '23-outflank',
        '17-compass',
    ];

    private function reveal(Scenario $scenario, string $province): void
    {
        self::service(RevealProvinceHandler::class)(new RevealProvince(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card($province)->getId(),
        ));
    }

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

    public function testEveryCardReactingToAProvinceRevealedSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(ProvinceRevealedEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testEachProvinceReactsToItsOwnRevealOnly(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('12-flooded-waste', as: 'waste')
                ->province('36-brushfires', as: 'brushfires'))
            ->persist();

        $this->reveal($scenario, 'waste');

        // Brushfires stays face down
        self::assertSame(['waste'], $this->offered($scenario, 'jane', ['waste', 'brushfires']));
    }

    public function testTheCardsReactingToAProvinceTheirPlayerControls(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['johns_chiari' => '31-kitsuki-chiari'])
                ->province('01-entrenched-position', as: 'johns_province'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['chiari' => '31-kitsuki-chiari'])
                ->inPlay(['tattooed' => '01-togashi-yokuni', 'tattoo' => '33-waterfall-tattoo'])
                ->province('01-entrenched-position', as: 'province'))
            ->persist();
        $aliases = ['johns_chiari', 'chiari', 'tattoo'];

        $this->reveal($scenario, 'province');

        self::assertSame(['chiari', 'tattoo'], $this->offered($scenario, 'jane', $aliases));
        self::assertSame([], $this->offered($scenario, 'john', $aliases));
    }

    public function testACharacterReactsToTheProvinceItAttacks(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['attacker' => '04-child-of-the-plains'])
                ->inPlay(['at_home' => '04-child-of-the-plains']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'attacked')
                ->province('01-entrenched-position', as: 'other'))
            ->conflict('john', 'attacked', attackers: ['attacker'])
            ->persist();

        // a province not attacked
        $this->reveal($scenario, 'other');
        self::assertSame([], $this->offered($scenario, 'john', ['attacker', 'at_home']));

        $this->reveal($scenario, 'attacked');
        self::assertSame(['attacker'], $this->offered($scenario, 'john', ['attacker', 'at_home']));
    }

    public function testTheCardsReactingToAnOpponentsProvinceDuringAConflict(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['trailblazer' => '17-shinjo-trailblazer'])
                ->inPlay(['attacker' => '01-togashi-yokuni', 'compass' => '17-compass'])
                ->inPlay(['at_home' => '01-togashi-yokuni', 'idle_compass' => '17-compass']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['janes_trailblazer' => '17-shinjo-trailblazer'])
                ->province('01-entrenched-position', as: 'attacked'))
            ->conflict('john', 'attacked', attackers: ['attacker'])
            ->persist();
        $aliases = ['trailblazer', 'compass', 'idle_compass', 'janes_trailblazer'];

        $this->reveal($scenario, 'attacked');

        // not the Compass of a character at home, nor the cards of the player whose province it is
        self::assertSame(['trailblazer', 'compass'], $this->offered($scenario, 'john', $aliases));
        self::assertSame([], $this->offered($scenario, 'jane', $aliases));
    }

    public function testNoCardReactsToAnOpponentsProvinceOutsideOfAConflict(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['trailblazer' => '17-shinjo-trailblazer'])
                ->hand(['outflank' => '23-outflank']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province'))
            ->persist();

        $this->reveal($scenario, 'province');

        self::assertSame([], $this->offered($scenario, 'john', ['trailblazer', 'outflank']));
    }
}
