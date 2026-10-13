<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Orientation;
use App\Message\ReadyCard;
use App\MessageHandler\ReadyCardHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Registry;

class ReadyCardHandlerTest extends AbstractKernelTestCase
{
    /**
     * Every card in play is bowed, except `ready`.
     */
    private function scenarioWithBowedCards(): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('01-yojin-no-shiro', as: 'stronghold')
                ->hand([
                    'banzai' => '01-banzai',
                ])
                ->inPlay([
                    'toturi' => '01-akodo-toturi',
                    'katana' => '01-fine-katana',
                    'ready' => '01-master-of-the-spear',
                ]))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay([
                'hotaru' => '01-doji-hotaru',
            ]))
            ->activePlayer('john')
            ->persist();

        $registry = self::service(Registry::class);
        foreach (['stronghold', 'toturi', 'katana', 'hotaru'] as $alias) {
            $card = $scenario->card($alias);
            $registry->get($card)->apply($card, 'bow');
        }

        return $scenario;
    }

    private function ready(Scenario $scenario, string $card, string $player = 'john'): void
    {
        self::service(ReadyCardHandler::class)(new ReadyCard(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testReadiesTheBowedCardsInPlay(): void
    {
        $scenario = $this->scenarioWithBowedCards();

        foreach (['toturi', 'katana', 'stronghold'] as $alias) {
            $card = $scenario->card($alias);
            $stack = $card->getStack();
            $place = $card->getCurrentPlace();

            $this->ready($scenario, $alias);

            self::assertSame(Orientation::FaceUp, $card->getOrientation(), $alias);
            self::assertSame($place, $card->getCurrentPlace(), $alias);
            self::assertSame($stack, $card->getStack(), $alias);
        }
    }

    public function testReadiesACardOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioWithBowedCards();

        $this->ready($scenario, 'hotaru');

        self::assertSame(Orientation::FaceUp, $scenario->card('hotaru')->getOrientation());
    }

    public function testAcceptsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->scenarioWithBowedCards();

        $this->ready($scenario, 'toturi', player: 'jane');

        self::assertSame(Orientation::FaceUp, $scenario->card('toturi')->getOrientation());
    }

    public function testRejectsAReadyCard(): void
    {
        $scenario = $this->scenarioWithBowedCards();

        $this->expectException(NotEnabledTransitionException::class);

        $this->ready($scenario, 'ready');
    }

    public function testRejectsACardInHand(): void
    {
        $scenario = $this->scenarioWithBowedCards();

        $this->expectException(NotEnabledTransitionException::class);

        $this->ready($scenario, 'banzai');
    }
}
