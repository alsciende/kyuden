<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\AbilityTiming;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Service\Interrupt\InterruptOfferer;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\GameBuilder;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Trigger\AttachmentBeingPlayedTrigger;
use App\Trigger\EffectsInitiatingTrigger;

/**
 * The condition of each card interrupting the effects of a card that would initiate (doc/interrupts.md, 2), on a
 * board holding them all: John's cards, Jane's effects unless stated.
 */
class EffectsInitiatingConditionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = [
        'voice', 'censure', 'forgery', 'edict', 'appeal', 'defend', 'yogo', 'mushin', 'highlight',
        'master', 'shiba', 'courtier', 'mantis', 'hantei', 'cursecatcher', 'jade', 'castle',
        'guard', 'deception', 'blade',
    ];

    /**
     * @param ?\Closure(GameBuilder): GameBuilder     $configure
     * @param ?\Closure(PlayerBuilder): PlayerBuilder $john      more of John
     */
    private function board(?\Closure $configure = null, ?\Closure $john = null): Scenario
    {
        $builder = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => ($john ?? fn (PlayerBuilder $same): PlayerBuilder => $same)($p
                ->honor(10)
                ->stronghold('34-iron-mountain-castle', as: 'castle')
                ->hand([
                    'voice' => '01-voice-of-honor', 'censure' => '04-censure', 'forgery' => '32-forgery', 'edict' => '01-forged-edict',
                    'appeal' => '28-appeal-to-sympathy', 'defend' => '16-defend-your-honor', 'yogo' => '08-yogo-kikuyo',
                    'mushin' => '39-mushin-no-shin', 'highlight' => '30-highlight-the-flaws', 'katana' => '01-fine-katana',
                ])
                ->inPlay(['master' => '33-master-of-the-court'], personalHonor: PersonalHonor::Honored)
                ->inPlay(['shiba' => '01-shiba-yojimbo'])
                ->inPlay(['courtier' => '01-asako-diplomat'])
                // a shugenja
                ->inPlay(['mantis' => '14-mantis-tenkinja', 'jade' => '02-finger-of-jade'])
                ->inPlay(['hantei' => '16-hantei-xxxviii'])
                ->inPlay(['cursecatcher' => '31-cursecatcher'])))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(11)
                ->hand(['banzai' => '01-banzai', 'spell' => '01-display-of-power'])
                ->inPlay(['hotaru' => '01-doji-hotaru'])
                ->province('01-kuroi-mori', as: 'mori', dynasty: '01-akodo-gunso', orientation: Orientation::FaceUp));

        return ($configure === null ? $builder : $configure($builder))->persist();
    }

    /**
     * The cards of ALIASES offered, by `player:stage`.
     *
     * @return array<string, list<string>>
     */
    private function offers(Scenario $scenario, EffectsInitiatingTrigger|AttachmentBeingPlayedTrigger $trigger): array
    {
        if (! self::service(InterruptOfferer::class)->open($scenario->game(), [$trigger])) {
            return [];
        }

        $window = self::service(GameStateManager::class)->getState($scenario->game())->currentInterruptWindow();
        $offers = [];
        foreach ($window->stages ?? [] as $stage) {
            foreach ($stage->cardIds as $playerId => $cardIds) {
                $aliases = array_values(array_filter(self::ALIASES, fn (string $candidate): bool => $this->isOffered($scenario, $candidate, $cardIds)));
                if ($aliases !== []) {
                    $player = $playerId === $scenario->player('john')->getId() ? 'john' : 'jane';
                    $offers["{$player}:{$stage->stage->value}"] = $aliases;
                }
            }
        }

        ksort($offers);

        return $offers;
    }

    /**
     * @param list<string> $cardIds
     */
    private function isOffered(Scenario $scenario, string $alias, array $cardIds): bool
    {
        try {
            return in_array($scenario->card($alias)->getId(), $cardIds, true);
        } catch (\OutOfBoundsException) {
            // not on this board
            return false;
        }
    }

    private function event(Scenario $scenario, string $alias, string $player = 'jane'): EffectsInitiatingTrigger
    {
        return new EffectsInitiatingTrigger($scenario->card($alias), $scenario->player($player), true, AbilityTiming::Action);
    }

    public function testAnEventOfTheOpponent(): void
    {
        $scenario = $this->board();

        self::assertSame([
            // Hantei XXXVIII: any ability, whatever its targets
            'john:standard' => ['hantei'],
            // Censure without the Imperial Favor, Defend Your Honor and Yogo Kikuyo outside a conflict, Mushin no Shin
            // without a character holding 2 attachments: not offered
            'john:would' => ['voice', 'forgery', 'edict', 'appeal', 'master', 'shiba', 'jade'],
        ], $this->offers($scenario, $this->event($scenario, 'banzai')));
    }

    public function testTheirConditionsChange(): void
    {
        $scenario = $this->board(
            fn (GameBuilder $builder): GameBuilder => $builder
                ->conflict('jane', 'mori')
                ->withGameState(function (GameState $state, Scenario $scenario): void {
                    $state->imperialFavorHolderId = $scenario->player('john')->getId();
                }),
            fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(12)
                ->inPlay(['busy' => '01-hida-kisada', 'fan' => '01-ornate-fan', 'katana2' => '01-fine-katana']),
        );

        self::assertSame([
            'john:standard' => ['hantei'],
            // a Spell event during a conflict: Yogo Kikuyo; John more honorable: no Forgery
            'john:would' => ['voice', 'censure', 'edict', 'appeal', 'defend', 'yogo', 'mushin', 'master', 'shiba', 'jade'],
        ], $this->offers($scenario, $this->event($scenario, 'spell')));
    }

    public function testTheirOwnEvent(): void
    {
        $scenario = $this->board();

        // Mantis Tenkinja reduces its cost; nothing cancels John's own effects
        self::assertSame(['john:standard' => ['mantis']], $this->offers($scenario, $this->event($scenario, 'voice', 'john')));
    }

    public function testAProvinceAbilityOfTheOpponent(): void
    {
        $scenario = $this->board();
        $trigger = new EffectsInitiatingTrigger($scenario->card('mori'), $scenario->player('jane'), false, AbilityTiming::Action);

        self::assertSame([
            'john:standard' => ['hantei'],
            // a face-down card in the province: Cursecatcher
            'john:would' => ['highlight', 'shiba', 'cursecatcher', 'jade'],
        ], $this->offers($scenario, $trigger));
    }

    public function testAnAttachmentPlayedOnTheirCharacter(): void
    {
        $scenario = $this->board();

        self::assertSame(['john:standard' => ['castle']], $this->offers($scenario, new AttachmentBeingPlayedTrigger($scenario->card('katana'), $scenario->player('john'), $scenario->card('shiba'))));
        // on a character of the opponent
        self::assertSame([], $this->offers($scenario, new AttachmentBeingPlayedTrigger($scenario->card('katana'), $scenario->player('john'), $scenario->card('hotaru'))));
    }

    public function testACharacterAbilityAtTheirProvince(): void
    {
        $scenario = $this->board(
            fn (GameBuilder $builder): GameBuilder => $builder->conflict('jane', 'deception', attackers: ['hotaru']),
            fn (PlayerBuilder $p): PlayerBuilder => $p
                // Seppun Hidden Guard: Hida Kisada is unique
                ->inPlay(['guard' => '16-seppun-hidden-guard'])
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->province('21-effective-deception', as: 'deception', orientation: Orientation::FaceUp),
        );
        $trigger = new EffectsInitiatingTrigger($scenario->card('hotaru'), $scenario->player('jane'), false, AbilityTiming::Action);

        self::assertSame([
            'john:standard' => ['hantei'],
            'john:would' => ['shiba', 'jade', 'guard', 'deception'],
        ], $this->offers($scenario, $trigger));
    }

    public function testAnAbilityOfTheAttackedProvinceAgainstTheirAttacker(): void
    {
        $scenario = $this->board(
            fn (GameBuilder $builder): GameBuilder => $builder->conflict('john', 'mori', attackers: ['kisada']),
            fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['kisada' => '01-hida-kisada', 'blade' => '03-pathfinder-s-blade']),
        );
        $trigger = new EffectsInitiatingTrigger($scenario->card('mori'), $scenario->player('jane'), false, AbilityTiming::Action);

        self::assertSame([
            'john:standard' => ['hantei'],
            'john:would' => ['highlight', 'shiba', 'cursecatcher', 'jade', 'blade'],
        ], $this->offers($scenario, $trigger));
    }
}
