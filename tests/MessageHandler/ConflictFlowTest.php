<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Message\DeclareConflict;
use App\Message\DeclareConflictSkill;
use App\Message\DeclareDefenders;
use App\Message\GainHonor;
use App\Message\PassOpportunity;
use App\Message\RevealProvince;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * A whole conflict through the message bus, from its declaration back to the action window of the conflict phase.
 */
class ConflictFlowTest extends AbstractKernelTestCase
{
    private Scenario $scenario;

    private function id(string $player): string
    {
        return $this->scenario->player($player)->getId();
    }

    private function card(string $alias): string
    {
        return $this->scenario->card($alias)->getId();
    }

    private function gameId(): string
    {
        return $this->scenario->game()->getId();
    }

    private function bothPass(string $first = 'john', string $second = 'jane'): void
    {
        $this->messageBus->dispatch(new PassOpportunity($this->gameId(), $this->id($first)));
        $this->messageBus->dispatch(new PassOpportunity($this->gameId(), $this->id($second)));
    }

    private function assertPlace(string $place): void
    {
        self::assertSame($place, $this->scenario->game()->getCurrentPlace());
    }

    public function testAConflictWonByTheAttackingPlayer(): void
    {
        $this->scenario = $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province'))
            ->activePlayer('john')
            ->persist();

        $this->messageBus->dispatch(new DeclareConflict($this->gameId(), $this->id('john'), Element::Air, ConflictType::Military, $this->card('province'), [$this->card('toturi')]));
        $this->assertPlace('reveal_attacked_province');
        $this->messageBus->dispatch(new RevealProvince($this->gameId(), $this->id('jane'), $this->card('province')));

        $this->assertPlace('react_to_conflict_declaration');
        $this->bothPass();

        $this->assertPlace('declare_defenders');
        $this->messageBus->dispatch(new DeclareDefenders($this->gameId(), $this->id('jane'), [$this->card('spear')]));

        $this->assertPlace('conflict_action_window');
        $this->bothPass('jane', 'john');

        $this->assertPlace('determine_result');
        $strength = (int) $this->scenario->card('province')->getLogicalCard()->getStrength();
        $this->messageBus->dispatch(new DeclareConflictSkill($this->gameId(), $this->id('john'), $strength + 1));
        $this->messageBus->dispatch(new DeclareConflictSkill($this->gameId(), $this->id('jane'), 1));

        $this->assertPlace('react_to_conflict_result');
        $this->bothPass();

        $this->assertPlace('react_to_broken_province');
        self::assertSame(ProvinceCard::STATE_BROKEN, $this->scenario->card('province')->getCurrentPlace());
        $this->bothPass();

        // the air ring: gain 2 honor
        $this->assertPlace('resolve_ring_effects');
        $this->messageBus->dispatch(new GainHonor($this->gameId(), $this->id('john'), $this->id('john'), 2));
        $this->messageBus->dispatch(new PassOpportunity($this->gameId(), $this->id('john')));

        $this->assertPlace('react_to_ring_claim');
        $this->bothPass();

        $this->assertPlace('conflict_phase_action_window');
        $state = self::service(GameStateManager::class)->getState($this->scenario->game());
        self::assertNull($state->conflict);
        self::assertSame($this->id('john'), $state->getRing(Element::Air)->claimedById);
        self::assertSame(12, $this->playerStateManager->getState($this->scenario->player('john'))->getHonor());
        self::assertSame(Orientation::Sideways, $this->scenario->card('toturi')->getOrientation());
        self::assertSame(Orientation::Sideways, $this->scenario->card('spear')->getOrientation());
    }
}
