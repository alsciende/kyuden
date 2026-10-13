<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Enum\Orientation;
use App\Message\BowCard;
use App\MessageHandler\BowCardHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Exception\UndefinedTransitionException;
use Symfony\Component\Workflow\Registry;

class BowCardHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithCardsInPlay(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('01-yojin-no-shiro', as: 'stronghold')
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp)
                ->hand([
                    'banzai' => '01-banzai',
                ])
                ->inPlay([
                    'toturi' => '01-akodo-toturi',
                    'katana' => '01-fine-katana',
                ]))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay([
                'hotaru' => '01-doji-hotaru',
            ]))
            ->activePlayer('john')
            ->persist();
    }

    private function bow(Scenario $scenario, string $card, string $player = 'john'): void
    {
        self::service(BowCardHandler::class)(new BowCard(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testBowsTheCardsInPlay(): void
    {
        $scenario = $this->scenarioWithCardsInPlay();

        foreach (['toturi', 'katana', 'stronghold'] as $alias) {
            $card = $scenario->card($alias);
            $stack = $card->getStack();
            $place = $card->getCurrentPlace();

            $this->bow($scenario, $alias);

            self::assertSame(Orientation::Sideways, $card->getOrientation(), $alias);
            self::assertSame($place, $card->getCurrentPlace(), $alias);
            self::assertSame($stack, $card->getStack(), $alias);
        }
    }

    public function testBowsACardOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioWithCardsInPlay();

        $this->bow($scenario, 'hotaru');

        self::assertSame(Orientation::Sideways, $scenario->card('hotaru')->getOrientation());
        self::assertSame(DynastyCard::STATE_IN_PLAY, $scenario->card('hotaru')->getCurrentPlace());
    }

    public function testAcceptsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->scenarioWithCardsInPlay();

        $this->bow($scenario, 'toturi', player: 'jane');

        self::assertSame(Orientation::Sideways, $scenario->card('toturi')->getOrientation());
    }

    public function testRejectsABowedCard(): void
    {
        $scenario = $this->scenarioWithCardsInPlay();
        $toturi = $scenario->card('toturi');
        self::service(Registry::class)->get($toturi)->apply($toturi, 'bow');

        $this->expectException(NotEnabledTransitionException::class);

        $this->bow($scenario, 'toturi');
    }

    public function testRejectsACardInHand(): void
    {
        $scenario = $this->scenarioWithCardsInPlay();

        $this->expectException(NotEnabledTransitionException::class);

        $this->bow($scenario, 'banzai');
    }

    public function testRejectsAProvince(): void
    {
        $scenario = $this->scenarioWithCardsInPlay();

        $this->expectException(UndefinedTransitionException::class);

        $this->bow($scenario, 'province');
    }
}
