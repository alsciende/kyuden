<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Service\ControlService;
use App\Service\Interrupt\InterruptOfferer;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Trigger\CardLeavingPlayTrigger;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The condition of each card interrupting a card leaving play (doc/interrupts.md, 1), on a board holding them all.
 */
class CardLeavingPlayConditionsTest extends AbstractKernelTestCase
{
    /**
     * The characters interrupting their own leaving play.
     */
    private const array THIS_CHARACTER = ['fawning', 'callow', 'fuzake', 'philosopher', 'entertainer', 'aide', 'disciple', 'sumiko'];

    private const array ALIASES = [
        ...self::THIS_CHARACTER,
        'host', 'tsuruhashi', 'daughter', 'youth', 'watch', 'master', 'mine', 'hideaway', 'pledge', 'duty', 'hotaru', 'berserker',
    ];

    private function board(): Scenario
    {
        return $this->scenario()
            ->atPlace('fate_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                // scorpion, cost 3
                ->inPlay(['fawning' => '05-fawning-diplomat'])
                // crane, cost 1
                ->inPlay(['callow' => '13-callow-delegate'], personalHonor: PersonalHonor::Honored)
                ->inPlay(['fuzake' => '18-yasuki-fuzake'])
                ->inPlay(['philosopher' => '30-traveling-philosopher'])
                ->inPlay(['entertainer' => '33-beautiful-entertainer'])
                ->inPlay(['aide' => '28-chancellor-s-aide'])
                ->inPlay(['disciple' => '30-disciple-of-shinsei'])
                // cost 4
                ->inPlay(['sumiko' => '34-agasha-sumiko'])
                // cost 5
                ->inPlay(['host' => '01-hida-kisada', 'tsuruhashi' => '22-sharpened-tsuruhashi', 'daughter' => '36-daughter-of-war', 'youth' => '39-promising-youth'])
                ->inPlay(['watch' => '19-fifth-tower-watch'])
                ->inPlay(['master' => '33-master-of-many-lifetimes'])
                // 3 unbroken provinces, 1 broken
                ->province('01-entrenched-position', dynasty: '07-iron-mine', dynastyAs: 'mine', dynastyFaceup: true, orientation: Orientation::FaceUp)
                ->province('01-entrenched-position', dynasty: '04-back-alley-hideaway', dynastyAs: 'hideaway', dynastyFaceup: true, orientation: Orientation::FaceUp)
                ->province('29-pledge-of-loyalty', as: 'pledge', orientation: Orientation::FaceUp)
                ->province('01-entrenched-position', orientation: Orientation::UpsideDown)
                ->hand(['duty' => '33-ceaseless-duty']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                // cost 5
                ->inPlay(['hotaru' => '01-doji-hotaru'])
                // cost 1
                ->inPlay(['berserker' => '01-matsu-berserker']))
            ->persist();
    }

    /**
     * The cards of ALIASES offered for the card leaving play, by alias and stage.
     *
     * @return array<string, list<string>> the aliases, by `player:stage`
     */
    private function offers(Scenario $scenario, string $alias, bool $sacrificed = false): array
    {
        $card = $scenario->card($alias);
        $character = $card->isCharacterInPlay();
        $triggers = [new CardLeavingPlayTrigger($card, self::service(ControlService::class)->getController($card), $character, $sacrificed)];
        if (! self::service(InterruptOfferer::class)->open($scenario->game(), $triggers)) {
            return [];
        }

        $window = self::service(GameStateManager::class)->getState($scenario->game())->currentInterruptWindow();
        $offers = [];
        foreach ($window->stages ?? [] as $stage) {
            foreach ($stage->cardIds as $playerId => $cardIds) {
                $aliases = array_values(array_filter(self::ALIASES, fn (string $candidate): bool => in_array($scenario->card($candidate)->getId(), $cardIds, true)));
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
     * @return iterable<string, array{string, list<string>}>
     */
    public static function thisCharacter(): iterable
    {
        yield 'Fawning Diplomat' => ['fawning', ['mine', 'hideaway', 'duty', 'master']];
        yield 'Callow Delegate' => ['callow', ['mine', 'pledge', 'duty', 'master']];
        yield 'Yasuki Fuzake' => ['fuzake', ['mine', 'duty', 'master']];
        yield 'Traveling Philosopher' => ['philosopher', ['mine', 'duty', 'master']];
        yield 'Beautiful Entertainer' => ['entertainer', ['mine', 'hideaway', 'duty', 'master']];
        yield "Chancellor's Aide" => ['aide', ['mine', 'duty', 'master']];
        yield 'Disciple of Shinsei' => ['disciple', ['mine', 'duty', 'master']];
        yield 'Agasha Sumiko' => ['sumiko', ['mine', 'master']];
    }

    /**
     * Each one interrupts its own leaving play only; the replacements depend on the character.
     *
     * @param list<string> $would
     */
    #[DataProvider('thisCharacter')]
    public function testThisCharacterLeavesPlay(string $alias, array $would): void
    {
        $offers = $this->offers($this->board(), $alias);

        self::assertSame([$alias], $offers['john:standard'] ?? []);
        self::assertEqualsCanonicalizing($would, $offers['john:would'] ?? []);
    }

    public function testTheAttachedCharacterLeavesPlay(): void
    {
        self::assertSame([
            'john:standard' => ['daughter', 'youth'],
            // cost 5: more than the unbroken provinces
            'john:would' => ['master', 'mine'],
        ], $this->offers($this->board(), 'host'));
    }

    public function testTheAttachedCharacterIsSacrificed(): void
    {
        self::assertSame([
            'john:standard' => ['tsuruhashi', 'daughter', 'youth', 'watch'],
            'john:would' => ['master', 'mine'],
        ], $this->offers($this->board(), 'host', sacrificed: true));
    }

    public function testAnAttachmentLeavingPlayIsNoCharacter(): void
    {
        self::assertSame([], $this->offers($this->board(), 'tsuruhashi'));
    }

    public function testACharacterOfTheOpponent(): void
    {
        // Ceaseless Duty only, any character whose cost is not more than the unbroken provinces (3, the broken one left out)
        self::assertSame(['john:would' => ['duty']], $this->offers($this->board(), 'berserker'));
        self::assertSame([], $this->offers($this->board(), 'hotaru'));
    }
}
