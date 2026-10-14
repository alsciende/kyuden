<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Data\WorkflowException;
use App\Exception\Rules\HonorAlreadyBidException;
use App\Exception\Rules\DuelStepException;
use App\Exception\Rules\NotInDuelException;
use App\Message\BidHonor;
use App\MessageHandler\BidHonorHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class BidHonorHandlerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): mixed|null $john
     */
    private function scenarioAtHonorBid(?\Closure $john = null, string $place = 'honor_bid'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', $john)
            ->withPlayer('jane')
            ->persist();
    }

    private function bid(Scenario $scenario, string $player, int $bid): BidHonor
    {
        return new BidHonor($scenario->game()->getId(), $scenario->player($player)->getId(), $bid);
    }

    public function testRecordsTheBid(): void
    {
        $scenario = $this->scenarioAtHonorBid();

        self::service(BidHonorHandler::class)($this->bid($scenario, 'john', 3));

        self::assertSame(3, $this->playerStateManager->getState($scenario->player('john'))->getPendingHonorBid());
        self::assertNull($this->playerStateManager->getState($scenario->player('jane'))->getPendingHonorBid());
    }

    public function testAnyPlayerCanBidFirst(): void
    {
        $scenario = $this->scenarioAtHonorBid();

        self::service(BidHonorHandler::class)($this->bid($scenario, 'jane', 1));

        self::assertSame(1, $this->playerStateManager->getState($scenario->player('jane'))->getPendingHonorBid());
    }

    public function testRejectsASecondBidInTheSameRound(): void
    {
        $scenario = $this->scenarioAtHonorBid(fn (PlayerBuilder $p): PlayerBuilder => $p->pendingHonorBid(2));

        $this->expectException(HonorAlreadyBidException::class);

        self::service(BidHonorHandler::class)($this->bid($scenario, 'john', 4));
    }

    public function testRejectsABidBelowOne(): void
    {
        $scenario = $this->scenarioAtHonorBid();

        $this->expectException(\InvalidArgumentException::class);

        self::service(BidHonorHandler::class)($this->bid($scenario, 'john', 0));
    }

    public function testRejectsABidAboveFive(): void
    {
        $scenario = $this->scenarioAtHonorBid();

        $this->expectException(\InvalidArgumentException::class);

        self::service(BidHonorHandler::class)($this->bid($scenario, 'john', 6));
    }

    public function testRejectsABidOutsideTheHonorBidStep(): void
    {
        $scenario = $this->scenarioAtHonorBid(place: 'draw_cards');

        $this->expectException(WorkflowException::class);

        self::service(BidHonorHandler::class)($this->bid($scenario, 'john', 3));
    }

    public function testHonorDialsAreRevealedOnceTheLastPlayerHasBid(): void
    {
        $scenario = $this->scenarioAtHonorBid(fn (PlayerBuilder $p): PlayerBuilder => $p->pendingHonorBid(2));

        // through the bus, so that automatic transitions happen
        $this->messageBus->dispatch($this->bid($scenario, 'jane', 4));

        self::assertSame('reveal_honor_dials', $scenario->game()->getCurrentPlace());
        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getHonorBid());
        self::assertSame(4, $this->playerStateManager->getState($scenario->player('jane'))->getHonorBid());
    }

    private function scenarioInDuel(bool $revealed = false): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['spear' => '01-master-of-the-spear']))
            ->withPlayer('jack')
            ->duel('john', ['toturi'], ['spear'], revealed: $revealed)
            ->persist();
    }

    public function testRecordsTheBidOfAPlayerInvolvedInADuel(): void
    {
        $scenario = $this->scenarioInDuel();

        self::service(BidHonorHandler::class)($this->bid($scenario, 'jane', 5));

        self::assertSame(5, $this->playerStateManager->getState($scenario->player('jane'))->getPendingHonorBid());
    }

    public function testRejectsTheBidOfAPlayerNotInvolvedInTheDuel(): void
    {
        $scenario = $this->scenarioInDuel();

        $this->expectException(NotInDuelException::class);

        self::service(BidHonorHandler::class)($this->bid($scenario, 'jack', 3));
    }

    public function testRejectsABidOnceTheHonorDialsOfTheDuelAreRevealed(): void
    {
        $scenario = $this->scenarioInDuel(revealed: true);

        $this->expectException(DuelStepException::class);

        self::service(BidHonorHandler::class)($this->bid($scenario, 'john', 3));
    }
}
