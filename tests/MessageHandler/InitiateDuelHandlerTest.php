<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\DuelType;
use App\Enum\Orientation;
use App\Exception\Rules\CannotDuelException;
use App\Exception\Rules\DuelInProgressException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInOpportunitiesStepException;
use App\Exception\Rules\NotParticipatingException;
use App\Message\InitiateDuel;
use App\MessageHandler\InitiateDuelHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\GameBuilder;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class InitiateDuelHandlerTest extends AbstractKernelTestCase
{
    private function scenarioInActionWindow(string $place = 'draw_phase_action_window'): GameBuilder
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honorBid(3)->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honorBid(1)->inPlay(['spear' => '01-master-of-the-spear', 'hotaru' => '01-doji-hotaru']))
            ->activePlayer('john');
    }

    /**
     * @param list<string> $challengers
     * @param list<string> $challenged
     */
    private function initiate(Scenario $scenario, array $challengers = ['toturi'], array $challenged = ['spear'], string $player = 'john'): void
    {
        self::service(InitiateDuelHandler::class)(new InitiateDuel(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            DuelType::Political,
            array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $challengers),
            array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $challenged),
        ));
    }

    public function testRecordsTheDuel(): void
    {
        $scenario = $this->scenarioInActionWindow()->persist();

        $this->initiate($scenario);

        $duel = self::service(GameStateManager::class)->getState($scenario->game())->currentDuel();
        self::assertSame($scenario->player('john')->getId(), $duel->challengerId);
        self::assertSame($scenario->player('jane')->getId(), $duel->challengedId);
        self::assertSame(DuelType::Political, $duel->type);
        self::assertSame([$scenario->card('toturi')->getId()], $duel->challengerCardIds);
        self::assertSame([$scenario->card('spear')->getId()], $duel->challengedCardIds);
        self::assertTrue($duel->atBidStep());
    }

    public function testANewHonorBidBeginsForThePlayersInvolved(): void
    {
        $scenario = $this->scenarioInActionWindow()->persist();

        $this->initiate($scenario);

        foreach (['john', 'jane'] as $alias) {
            self::assertNull($this->playerStateManager->getState($scenario->player($alias))->getHonorBid());
        }
    }

    public function testRejectsAPlayerWhoDoesNotHaveTheOpportunityToAct(): void
    {
        $scenario = $this->scenarioInActionWindow()->persist();

        $this->expectException(NotActivePlayerException::class);

        $this->initiate($scenario, ['spear'], ['toturi'], player: 'jane');
    }

    public function testRejectsADuelOutsideAStepWithOpportunities(): void
    {
        $scenario = $this->scenarioInActionWindow('draw_cards')->persist();

        $this->expectException(NotInOpportunitiesStepException::class);

        $this->initiate($scenario);
    }

    public function testRejectsASecondDuel(): void
    {
        $scenario = $this->scenarioInActionWindow()->duel('john', ['toturi'], ['spear'])->persist();

        $this->expectException(DuelInProgressException::class);

        $this->initiate($scenario);
    }

    public function testRejectsAChallengedCharacterControlledByThePlayer(): void
    {
        $scenario = $this->scenarioInActionWindow()->persist();

        $this->expectException(CannotDuelException::class);

        $this->initiate($scenario, ['toturi'], ['toturi']);
    }

    public function testDuringAConflictEveryCharacterParticipates(): void
    {
        $scenario = $this->scenarioInActionWindow('conflict_action_window')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honorBid(1)
                ->inPlay(['spear' => '01-master-of-the-spear', 'hotaru' => '01-doji-hotaru'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['toturi'], defenders: ['spear'])
            ->persist();

        $this->expectException(NotParticipatingException::class);

        $this->initiate($scenario, ['toturi'], ['hotaru']);
    }
}
