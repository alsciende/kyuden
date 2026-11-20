<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Event\ConflictWonEvent;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The cards reacting to a conflict won or lost ("After this character wins a conflict", "After you lose a conflict"…):
 * the conditions of their scripts.
 */
class ConflictWonReactionsTest extends AbstractKernelTestCase
{
    /**
     * The cards of the Stronghold format, the forced reactions included (Unmatched Expertise, Hall of Victories, Total
     * Warfare, Mercenary Company), but Mindful Warrior (the rings claimed on the provinces, of the Enlightenment format).
     */
    private const array CODES = [
        // the forced reactions (ForcedReactionsTest)
        '16-unmatched-expertise', '27-hall-of-victories', '27-total-warfare', '32-mercenary-company',
        '01-asako-diplomat', '01-blackmail-artist', '01-deathseeker', '01-defend-the-wall', '01-display-of-power',
        '01-fallen-in-battle', '01-hida-tomonatsu', '01-honored-blade', '01-ikoma-eiji', '01-indomitable-will',
        '01-keeper-of-air', '01-keeper-of-earth', '01-keeper-of-fire', '01-keeper-of-void', '01-keeper-of-water',
        '01-spies-at-court', '01-vengeful-oathkeeper', '02-gifted-tactician', '02-staunch-hida', '02-time-for-war',
        '07-kuni-ritsuko', '08-the-path-of-man', '09-hisu-mori-toride', '11-icon-of-favor', '11-soshi-shiori',
        '14-hisu-mori-toride', '14-minami-kaze-regulars', '14-roving-michibiku', '16-gift-of-amaterasu',
        '16-honed-nodachi', '16-midnight-prowler', '16-solitary-strength', '16-the-fires-of-justice', '17-spoils-of-war',
        '17-utaku-battle-steed', '18-akodo-makoto', '18-letter-from-the-daimyo', '19-blade-of-10-000-battles',
        '19-false-loyalties', '20-benevolent-ambassador', '20-utaku-rumaru', '21-court-of-justice', '21-hida-sugi',
        '21-shosuro-ibuki', '22-licensed-quarter', '22-togashi-yoshi', '23-quarrelsome-youth', '23-unified-company',
        '24-daidoji-harrier', '24-golden-plains-raider', '25-hida-kotoe', '26-hida-o-ushi', '26-purifier-apprentice',
        '26-seventh-tower', '26-slovenly-scavenger', '26-watchtower-of-valor', '27-champions-of-yomi',
        '27-embrace-death', '27-kenson-no-gakka', '27-kitsuki-kagi', '27-kyuden-ikoma', '27-matsu-tsuko',
        '28-kuni-silencer', '29-garanto-guardian', '29-kakita-yoshi', '31-return-from-shadows', '31-studious',
        '31-third-whisker-sneak', '31-way-with-words', '32-kyofuki-s-hammer', '32-sturdy-tetsubo',
        '33-fearless-skirmisher', '33-seven-fold-palace', '36-asako-maezawa', '36-chikara', '36-guardians-of-rokugan',
        '36-self-understanding', '37-raze-to-the-ground', '39-untainted', '40-higashi-kaze-company',
        '41-cleanse-the-empire', '41-daidoji-iron-warrior', '41-inquisitorial-initiate', '41-setting-the-standard',
        '42-hida-backbreaker', '42-iron-warrior-vanguard', '42-jade-inlaid-katana', '42-scarlet-sabre',
    ];

    /**
     * The result of the conflict of the scenario, from the skills of both sides, as `result_determined` gives it.
     */
    private function determineResult(Scenario $scenario, int $attackerSkill, int $defenderSkill): void
    {
        $game = $scenario->game();
        self::service(GameStateManager::class)->withState($game, function (GameState $state) use ($attackerSkill, $defenderSkill): GameState {
            $conflict = $state->currentConflict();
            $conflict->attackerSkill = $attackerSkill;
            $conflict->defenderSkill = $defenderSkill;
            $conflict->determineWinner();

            return $state;
        });
        $winnerId = self::service(GameStateManager::class)->getState($game)->currentConflict()->winnerId;
        self::assertNotNull($winnerId);
        self::service(EventDispatcherInterface::class)->dispatch(new ConflictWonEvent($game, $game->getPlayer($winnerId), $attackerSkill, $defenderSkill));
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

    public function testEveryCardReactingToAConflictWonOrLostSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(ConflictWonEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testTheCharactersOfTheWinningSide(): void
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_conflict_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['diplomat' => '01-asako-diplomat'])
                ->inPlay(['at_home' => '01-asako-diplomat'])
                ->inPlay(['bearer' => '01-togashi-yokuni', 'blade' => '01-honored-blade'])
                ->inPlay(['deathseeker' => '01-deathseeker']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['tomonatsu' => '01-hida-tomonatsu'])
                ->inPlay(['janes_deathseeker' => '01-deathseeker'])
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['diplomat', 'bearer', 'deathseeker'], defenders: ['tomonatsu'])
            ->persist();
        $aliases = ['diplomat', 'at_home', 'blade', 'deathseeker', 'tomonatsu', 'janes_deathseeker'];

        $this->determineResult($scenario, 5, 2);

        // not the character at home, nor Deathseeker, which reacts to losing as an attacker
        self::assertSame(['diplomat', 'blade'], $this->offered($scenario, 'john', $aliases));
        // Hida Tomonatsu wins as a defender only
        self::assertSame([], $this->offered($scenario, 'jane', $aliases));
    }

    public function testTheCharactersOfTheLosingSide(): void
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_conflict_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['deathseeker' => '01-deathseeker'])
                ->hand(['oathkeeper' => '01-vengeful-oathkeeper']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['tomonatsu' => '01-hida-tomonatsu'])
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['deathseeker'], defenders: ['tomonatsu'], type: ConflictType::Political)
            ->persist();
        $aliases = ['deathseeker', 'oathkeeper', 'tomonatsu'];

        $this->determineResult($scenario, 1, 3);

        // Vengeful Oathkeeper reacts to a military conflict lost
        self::assertSame(['deathseeker'], $this->offered($scenario, 'john', $aliases));
        self::assertSame(['tomonatsu'], $this->offered($scenario, 'jane', $aliases));
    }

    public function testTheCardsReactingFromOutOfPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_conflict_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['attacker' => '01-togashi-yokuni'])
                ->hand(['oathkeeper' => '01-vengeful-oathkeeper'])
                ->dynastyDiscardPile(['yomi' => '27-champions-of-yomi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['defender' => '01-hida-tomonatsu'])
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['attacker'], defenders: ['defender'])
            ->persist();

        $this->determineResult($scenario, 1, 3);

        // Vengeful Oathkeeper from the hand, Champions of Yomi from the dynasty discard pile
        self::assertSame(['oathkeeper', 'yomi'], $this->offered($scenario, 'john', ['oathkeeper', 'yomi']));
    }

    public function testTheConditionsOnTheResult(): void
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_conflict_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['attacker' => '01-togashi-yokuni'])
                ->hand([
                    'path' => '08-the-path-of-man',
                    'fallen' => '01-fallen-in-battle',
                    'shadows' => '31-return-from-shadows',
                    'spies' => '01-spies-at-court',
                ])
                ->role('01-keeper-of-air', as: 'keeper')
                ->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['display' => '01-display-of-power', 'loyalties' => '19-false-loyalties'])
                ->honor(5)
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['attacker'], ring: Element::Air)
            ->persist();
        $aliases = ['path', 'fallen', 'shadows', 'spies', 'keeper', 'display', 'loyalties'];

        $this->determineResult($scenario, 6, 0);

        // by 5 or more, military, unopposed; not political; the Keeper wins as the defending player only
        self::assertSame(['path', 'fallen', 'shadows'], $this->offered($scenario, 'john', $aliases));
        // an unopposed conflict lost; John, more honorable than Jane, wins it
        self::assertSame(['display', 'loyalties'], $this->offered($scenario, 'jane', $aliases));
    }

    public function testTheKeeperRoleReactsToTheConflictOfItsElementWonAsTheDefendingPlayer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_conflict_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['attacker' => '01-togashi-yokuni']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['defender' => '01-togashi-yokuni'])
                ->role('01-keeper-of-water', as: 'keeper')
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['attacker'], defenders: ['defender'], ring: Element::Water)
            ->persist();

        $this->determineResult($scenario, 1, 4);

        self::assertSame(['keeper'], $this->offered($scenario, 'jane', ['keeper']));
    }
}
