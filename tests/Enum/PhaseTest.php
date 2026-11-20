<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Entity\Game;
use App\Enum\Phase;
use App\Tests\AbstractKernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

class PhaseTest extends AbstractKernelTestCase
{
    /**
     * The places of the `game` workflow outside of the rounds: the setup, the end of the game.
     */
    private const array OUTSIDE_THE_ROUNDS = ['join_game', 'choose_stronghold', 'dynasty_mulligan', 'conflict_mulligan', Game::STATE_ENDED];

    public function testEveryPlaceOfTheRoundsBelongsToAPhase(): void
    {
        $workflow = self::getContainer()->get('state_machine.game');
        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        foreach ($workflow->getDefinition()->getPlaces() as $place) {
            if (in_array($place, self::OUTSIDE_THE_ROUNDS, true)) {
                self::assertNull(Phase::ofPlace($place), $place);
            } else {
                self::assertInstanceOf(Phase::class, Phase::ofPlace($place), $place);
            }
        }
    }

    public function testAPhaseBeginsAndEndsInItsOwnPlaces(): void
    {
        foreach (Phase::cases() as $phase) {
            self::assertSame($phase, Phase::ofPlace($phase->value.'_phase_begins'));
            self::assertSame($phase, Phase::ofPlace($phase->value.'_phase_ends'));
        }
    }
}
