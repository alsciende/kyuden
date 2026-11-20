<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\CardPlayedEvent;
use App\Message\PlayCharacter;
use App\Message\PlayCharacterFromProvince;
use App\Message\PutCharacterIntoPlay;
use App\MessageHandler\PlayCharacterFromProvinceHandler;
use App\MessageHandler\PlayCharacterHandler;
use App\MessageHandler\PutCharacterIntoPlayHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to a card played ("After you play this character", "After you play a [clan-crane] character"):
 * played, not put into play.
 */
class CardPlayedReactionsTest extends AbstractKernelTestCase
{
    /**
     * The cards reacting to a card played: a character or an attachment (CardPlayedReactionsTest), an event
     * (CardPlayedEventReactionsTest), whatever card (both).
     */
    private const array CODES = [
        '01-niten-master', '01-shosuro-miyako', '01-watch-commander', '02-meek-informant', '04-hiruma-skirmisher',
        '08-doji-shigeru', '08-shiba-tetsu', '09-isawa-uona', '09-the-mirror-s-gaze', '12-hawk-tattoo',
        '13-mark-of-shame', '15-ignoble-enforcers', '15-yasuki-oguri', '16-shinjo-ambusher', '17-agasha-taiko', '17-hida-tsuru',
        '17-aranat', '18-asahina-takamori', '18-benevolent-host', '18-winter-court-hosts', '19-sententious-poet',
        '21-doji-hotaru', '21-isawa-heiko', '21-shinjo-gunso', '21-shosuro-miyako', '23-erudite-prestige',
        '23-withstand-the-darkness', '25-dragon-tattoo', '25-togashi-acolyte', '25-togashi-gaijutsu',
        '34-worldly-shiotome', '38-iuchi-shahai', '42-audience-chamber', '42-field-tactician', '42-silent-enforcer',
        '42-togashi-dreamer',
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

    public function testEveryCardReactingToACardPlayedSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(CardPlayedEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testACraneCharacterOfPrintedCost4OrHigherPlayedFromAProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(10)
                ->province('01-entrenched-position', dynasty: '01-doji-hotaru', dynastyAs: 'hotaru', dynastyFaceup: true)
                ->province('01-entrenched-position', dynasty: '42-audience-chamber', dynastyAs: 'chamber', dynastyFaceup: true)
                ->inPlay(['takamori' => '18-asahina-takamori'])
                ->inPlay(['miyako' => '01-shosuro-miyako']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['janes_takamori' => '18-asahina-takamori']))
            ->activePlayer('john')
            ->persist();
        $aliases = ['chamber', 'takamori', 'miyako', 'janes_takamori'];

        self::service(PlayCharacterFromProvinceHandler::class)(new PlayCharacterFromProvince(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('hotaru')->getId(),
            5,
            0,
            0,
        ));

        // not Shosuro Miyako: not from the hand
        self::assertSame(['chamber', 'takamori'], $this->offered($scenario, 'john', $aliases));
        self::assertSame([], $this->offered($scenario, 'jane', $aliases));
    }

    public function testThisCharacterPlayedIntoTheConflictFromTheHand(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(10)
                ->inPlay(['attacker' => '01-togashi-initiate'])
                ->inPlay(['miyako' => '01-shosuro-miyako'])
                ->hand(['ambusher' => '16-shinjo-ambusher', 'informant' => '02-meek-informant']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['attacker'])
            ->activePlayer('john')
            ->persist();

        self::service(PlayCharacterHandler::class)(new PlayCharacter(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('ambusher')->getId(),
            0,
            0,
            0,
            true,
        ));

        // Meek Informant is still in the hand
        self::assertSame(['ambusher', 'miyako'], $this->offered($scenario, 'john', ['ambusher', 'miyako', 'informant']));
    }

    public function testACharacterPutIntoPlayIsNotPlayed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['miyako' => '01-shosuro-miyako'])
                ->hand(['informant' => '02-meek-informant']))
            ->withPlayer('jane')
            ->persist();

        self::service(PutCharacterIntoPlayHandler::class)(new PutCharacterIntoPlay(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('informant')->getId(),
            false,
        ));

        self::assertSame([], $this->offered($scenario, 'john', ['miyako', 'informant']));
    }
}
