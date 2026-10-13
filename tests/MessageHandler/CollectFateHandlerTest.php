<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\FateAlreadyCollectedException;
use App\Message\CollectFate;
use App\MessageHandler\CollectFateHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class CollectFateHandlerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): mixed|null $john
     */
    private function scenarioAtCollectFate(?\Closure $john = null): Scenario
    {
        return $this->scenario()
            ->atPlace('collect_fate')
            ->withPlayer('john', $john)
            ->withPlayer('jane')
            ->persist();
    }

    private function collect(Scenario $scenario, string $player, int $amount): CollectFate
    {
        return new CollectFate($scenario->game()->getId(), $scenario->player($player)->getId(), $amount);
    }

    public function testAddsTheAmountToTheFatePool(): void
    {
        $scenario = $this->scenarioAtCollectFate(fn (PlayerBuilder $p): PlayerBuilder => $p->fate(2));

        self::service(CollectFateHandler::class)($this->collect($scenario, 'john', 7));

        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(9, $state->getFate());
        self::assertTrue($state->isFateCollected());
    }

    public function testRejectsASecondCollectionInTheSameRound(): void
    {
        $scenario = $this->scenarioAtCollectFate(fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected());

        $this->expectException(FateAlreadyCollectedException::class);

        self::service(CollectFateHandler::class)($this->collect($scenario, 'john', 7));
    }

    public function testRejectsANegativeAmount(): void
    {
        $scenario = $this->scenarioAtCollectFate();

        $this->expectException(\InvalidArgumentException::class);

        self::service(CollectFateHandler::class)($this->collect($scenario, 'john', -1));
    }

    public function testCardsCanBePlayedOnceTheLastPlayerHasCollected(): void
    {
        $scenario = $this->scenarioAtCollectFate(fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected());

        // through the bus, so that automatic transitions happen
        $this->messageBus->dispatch($this->collect($scenario, 'jane', 7));

        self::assertSame('play_cards_from_provinces', $scenario->game()->getCurrentPlace());
    }
}
