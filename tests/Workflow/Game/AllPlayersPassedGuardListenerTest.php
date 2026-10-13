<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Workflow\Registry;

class AllPlayersPassedGuardListenerTest extends AbstractKernelTestCase
{
    private function canLeave(string $place, string $transition, bool $johnPassed, bool $janePassed): bool
    {
        $scenario = $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $johnPassed ? $p->passed() : $p)
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $janePassed ? $p->passed() : $p)
            ->build();

        return self::service(Registry::class)->get($scenario->game())->can($scenario->game(), $transition);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function steps(): iterable
    {
        yield 'play cards from provinces' => ['play_cards_from_provinces', 'dynasty_actions_passed'];
        yield 'reveal honor dials' => ['reveal_honor_dials', 'honor_dials_revealed'];
        yield 'draw phase action window' => ['draw_phase_action_window', 'draw_actions_passed'];
        yield 'conflict phase action window' => ['conflict_phase_action_window', 'conflict_phase_actions_passed'];
        yield 'reactions to the conflict declaration' => ['react_to_conflict_declaration', 'declaration_reactions_passed'];
        yield 'conflict action window' => ['conflict_action_window', 'conflict_actions_passed'];
        yield 'reactions to the conflict result' => ['react_to_conflict_result', 'result_reactions_passed'];
        yield 'reactions to the broken province' => ['react_to_broken_province', 'break_reactions_passed'];
        yield 'ring effects' => ['resolve_ring_effects', 'ring_effects_resolved'];
        yield 'reactions to the ring claim' => ['react_to_ring_claim', 'claim_reactions_passed'];
    }

    #[DataProvider('steps')]
    public function testBlockedWhileAPlayerHasNotPassed(string $place, string $transition): void
    {
        self::assertFalse($this->canLeave($place, $transition, johnPassed: true, janePassed: false));
    }

    #[DataProvider('steps')]
    public function testAllowedOnceEveryPlayerHasPassed(string $place, string $transition): void
    {
        self::assertTrue($this->canLeave($place, $transition, johnPassed: true, janePassed: true));
    }
}
