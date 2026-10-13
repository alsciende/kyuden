<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\TransferHonor;
use App\MessageHandler\TransferHonorHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class TransferHonorHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithHonor(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(12))
            ->persist();
    }

    private function transferHonor(Scenario $scenario, string $giver, string $recipient, int $amount): void
    {
        self::service(TransferHonorHandler::class)(new TransferHonor(
            $scenario->game()->getId(),
            $scenario->player($giver)->getId(),
            $scenario->player($recipient)->getId(),
            $amount,
        ));
    }

    public function testMovesHonorFromTheGiverToTheRecipient(): void
    {
        $scenario = $this->scenarioWithHonor();

        $this->transferHonor($scenario, 'john', 'jane', 2);

        self::assertSame(8, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
        self::assertSame(14, $this->playerStateManager->getState($scenario->player('jane'))->getHonor());
    }

    public function testRejectsANegativeAmount(): void
    {
        $scenario = $this->scenarioWithHonor();

        $this->expectException(\InvalidArgumentException::class);

        $this->transferHonor($scenario, 'john', 'jane', -2);
    }

    public function testRejectsATransferToThemselves(): void
    {
        $scenario = $this->scenarioWithHonor();

        $this->expectException(\InvalidArgumentException::class);

        $this->transferHonor($scenario, 'john', 'john', 2);
    }
}
