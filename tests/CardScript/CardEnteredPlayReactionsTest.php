<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\CardEnteredPlayEvent;
use App\Message\PlayAttachment;
use App\Message\PlayCharacter;
use App\Message\PlayCharacterFromProvince;
use App\Message\PutCharacterIntoPlay;
use App\MessageHandler\PlayAttachmentHandler;
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
 * The cards reacting to a character or an attachment entering play ("After this character enters play"), played or put
 * into play.
 */
class CardEnteredPlayReactionsTest extends AbstractKernelTestCase
{
    private const array CODES = [
        '01-akodo-gunso', '01-hiruma-ambusher', '01-honored-general', '01-iuchi-wayfinder', '01-matsu-beiona',
        '02-goblin-sneak', '02-kitsuki-yaruma', '03-shrine-maiden', '12-nezumi-infiltrator', '15-opium-wastrel',
        '17-iuchi-farseer', '22-truthseeker', '23-army-of-the-rising-wave', '25-elegant-tessen',
        '26-favorable-dealbroker', '26-kaiu-shihobu', '27-ashigaru-levy', '31-hunting-falcon',
        '34-apprentice-engineer', '34-doji-diplomat', '36-khanbulak-benefactor', '37-daidoji-uji',
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

    public function testEveryCardReactingToACardEnteringPlaySubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(CardEnteredPlayEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testTheCharacterPlayedFromAProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(10)
                ->province('01-entrenched-position', dynasty: '01-akodo-gunso', dynastyAs: 'gunso', dynastyFaceup: true)
                ->inPlay(['general' => '01-honored-general']))
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();

        self::service(PlayCharacterFromProvinceHandler::class)(new PlayCharacterFromProvince(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('gunso')->getId(),
            2,
            0,
            0,
        ));

        // not Honored General, already in play
        self::assertSame(['gunso'], $this->offered($scenario, 'john', ['gunso', 'general']));
    }

    public function testTheCharactersPlayedFromElsewhere(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(10)
                ->dynastyDiscardPile(['gunso' => '01-akodo-gunso'])
                ->hand(['infiltrator' => '12-nezumi-infiltrator']))
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();

        foreach (['gunso', 'infiltrator'] as $alias) {
            self::service(PlayCharacterHandler::class)(new PlayCharacter(
                $scenario->game()->getId(),
                $scenario->player('john')->getId(),
                $scenario->card($alias)->getId(),
                0,
                0,
                0,
                false,
            ));
        }

        // not from a province; not during a conflict
        self::assertSame([], $this->offered($scenario, 'john', ['gunso', 'infiltrator']));
    }

    public function testTheCharacterPutIntoPlayAsADefender(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['attacker' => '01-togashi-initiate'])
                ->hand(['johns_ambusher' => '01-hiruma-ambusher']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['ambusher' => '01-hiruma-ambusher'])
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['attacker'])
            ->persist();

        foreach (['johns_ambusher', 'ambusher'] as $alias) {
            self::service(PutCharacterIntoPlayHandler::class)(new PutCharacterIntoPlay(
                $scenario->game()->getId(),
                $scenario->player('john')->getId(),
                $scenario->card($alias)->getId(),
                true,
            ));
        }

        // john's Hiruma Ambusher attacks
        self::assertSame([], $this->offered($scenario, 'john', ['johns_ambusher', 'ambusher']));
        self::assertSame(['ambusher'], $this->offered($scenario, 'jane', ['johns_ambusher', 'ambusher']));
    }

    public function testTheAttachmentOnACharacterOfPrintedCost2OrLess(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['initiate' => '01-togashi-initiate'])
                ->inPlay(['beiona' => '01-matsu-beiona'])
                ->hand(['tessen' => '25-elegant-tessen', 'other_tessen' => '25-elegant-tessen']))
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();

        foreach (['tessen' => 'initiate', 'other_tessen' => 'beiona'] as $alias => $target) {
            self::service(PlayAttachmentHandler::class)(new PlayAttachment(
                $scenario->game()->getId(),
                $scenario->player('john')->getId(),
                $scenario->card($alias)->getId(),
                $scenario->card($target)->getId(),
                0,
            ));
        }

        // Matsu Beiona costs 3
        self::assertSame(['tessen'], $this->offered($scenario, 'john', ['tessen', 'other_tessen']));
    }
}
