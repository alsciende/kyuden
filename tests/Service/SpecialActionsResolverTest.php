<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ActionMessageRegistry;
use App\Service\SpecialActionsResolver;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class SpecialActionsResolverTest extends AbstractKernelTestCase
{
    private function scenarioWithCardsInPlay(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kaezin' => '01-kakita-kaezin', 'raitsugu' => '01-mirumoto-raitsugu', 'investigator' => '01-kitsuki-investigator']))
            ->withPlayer('jane')
            ->build();
    }

    public function testEverySpecialActionIsListedWithTheActiveCardsWhoseScriptNeedsIt(): void
    {
        $scenario = $this->scenarioWithCardsInPlay();

        $actions = self::service(SpecialActionsResolver::class)->resolve($scenario->game(), $scenario->player('john'));

        self::assertSame(self::service(ActionMessageRegistry::class)->getSpecialMessageTypes(), array_keys($actions));
        $duelists = [$scenario->card('kaezin')->getId(), $scenario->card('raitsugu')->getId()];
        sort($duelists);
        self::assertSame($duelists, $actions['initiate_duel']);
        self::assertSame([$scenario->card('investigator')->getId()], $actions['look_at_cards']);
        self::assertSame([$scenario->card('investigator')->getId()], $actions['move_fate']);
        self::assertSame([], $actions['switch_ring']);
    }

    public function testTheOpponentAndAReaderWhoIsNotAPlayerHaveNoActiveCard(): void
    {
        $scenario = $this->scenarioWithCardsInPlay();

        foreach ([$scenario->player('jane'), null] as $reader) {
            self::assertSame([], array_merge(...array_values(self::service(SpecialActionsResolver::class)->resolve($scenario->game(), $reader))));
        }
    }
}
