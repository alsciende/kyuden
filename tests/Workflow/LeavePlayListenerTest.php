<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;

class LeavePlayListenerTest extends AbstractKernelTestCase
{
    private function discardToturi(PersonalHonor $personalHonor = PersonalHonor::Ordinary): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'], fate: 2, personalHonor: $personalHonor)
                ->inPlay(['hotaru' => '01-doji-hotaru']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['toturi', 'hotaru'])
            ->build();

        self::service(Registry::class)->get($scenario->card('toturi'))->apply($scenario->card('toturi'), 'discard_card');

        return $scenario;
    }

    private function johnHonor(Scenario $scenario): ?int
    {
        return $this->playerStateManager->getState($scenario->player('john'))->getHonor();
    }

    public function testTheTokensOnTheCardReturnToTheTokenPool(): void
    {
        $scenario = $this->discardToturi(PersonalHonor::Dishonored);

        $state = self::service(CardStateManager::class)->getState($scenario->card('toturi'));
        self::assertSame(DynastyCard::STATE_DISCARD_PILE, $scenario->card('toturi')->getCurrentPlace());
        self::assertSame(0, $state->getFate());
        self::assertSame(PersonalHonor::Ordinary, $state->getPersonalHonor());
    }

    public function testTheControllerOfAnHonoredCharacterGainsOneHonor(): void
    {
        self::assertSame(11, $this->johnHonor($this->discardToturi(PersonalHonor::Honored)));
    }

    public function testTheControllerOfADishonoredCharacterLosesOneHonor(): void
    {
        self::assertSame(9, $this->johnHonor($this->discardToturi(PersonalHonor::Dishonored)));
    }

    public function testTheAttachmentsOnTheCharacterAreDiscarded(): void
    {
        $scenario = $this->discardToturi();

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('katana')->getCurrentPlace());
    }

    public function testTheCharacterNoLongerParticipatesInTheConflict(): void
    {
        $scenario = $this->discardToturi();

        $conflict = self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
        self::assertSame([$scenario->card('hotaru')->getId()], $conflict->attackerIds);
    }

    public function testBowingIsNotLeavingPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi'], fate: 2))
            ->build();

        self::service(Registry::class)->get($scenario->card('toturi'))->apply($scenario->card('toturi'), 'bow');

        self::assertSame(2, self::service(CardStateManager::class)->getState($scenario->card('toturi'))->getFate());
    }
}
