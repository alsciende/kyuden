<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Message\DiscardCard;
use App\MessageHandler\DiscardCardHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Registry;

class DiscardCardHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(): Scenario
    {
        return $this->scenario()
            ->atPlace('react_to_broken_province')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi')
                ->dynastyDeck(['refill' => '01-doji-hotaru'])
                ->hand(['katana' => '01-fine-katana'])
                ->conflictDiscardPile(['banzai' => '01-banzai']))
            ->persist();
    }

    private function discard(Scenario $scenario, string $card): void
    {
        self::service(DiscardCardHandler::class)(new DiscardCard(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testAnyPlayerDiscardsADynastyCardFromAProvince(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->discard($scenario, 'toturi');

        self::assertSame(DynastyCard::STATE_DISCARD_PILE, $scenario->card('toturi')->getCurrentPlace());
    }

    public function testAProvinceADynastyCardIsDiscardedFromIsRefilledFacedown(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->discard($scenario, 'toturi');

        self::assertSame($scenario->card('province')->getStack(), $scenario->card('refill')->getStack());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $scenario->card('refill')->getCurrentPlace());
    }

    public function testAProvinceIsNotRefilledWhileADynastyCardRemainsOnIt(): void
    {
        $scenario = $this->scenarioWithCards();
        // a second card on the province, as a holding can be
        self::service(Registry::class)->get($scenario->card('refill'))->apply($scenario->card('refill'), 'draw_card', [
            'stack' => $scenario->card('province')->getStack(),
        ]);

        $this->discard($scenario, 'toturi');

        self::assertCount(2, $scenario->card('province')->getStack()->getCards());
    }

    public function testAnyPlayerDiscardsACardFromHand(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->discard($scenario, 'katana');

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('katana')->getCurrentPlace());
    }

    public function testRejectsACardAlreadyInTheDiscardPile(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(NotEnabledTransitionException::class);

        $this->discard($scenario, 'banzai');
    }
}
