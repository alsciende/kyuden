<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Message\ReturnCardToHand;
use App\MessageHandler\ReturnCardToHandHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

class ReturnCardToHandHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['banzai' => '01-banzai'])
                ->inPlay([
                    'toturi' => '01-akodo-toturi',
                    'katana' => '01-fine-katana',
                ]))
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->persist();
    }

    private function returnToHand(Scenario $scenario, string $card): void
    {
        self::service(ReturnCardToHandHandler::class)(new ReturnCardToHand(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testAnyPlayerReturnsAConflictCardToTheHandOfItsOwner(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->returnToHand($scenario, 'katana');

        self::assertSame(ConflictCard::STATE_HAND, $scenario->card('katana')->getCurrentPlace());
        self::assertSame($scenario->player('john'), $scenario->card('katana')->getStack()->getPlayer());
    }

    public function testADynastyCardIsPutIntoTheDynastyDiscardPileInstead(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->returnToHand($scenario, 'toturi');

        self::assertSame(DynastyCard::STATE_DISCARD_PILE, $scenario->card('toturi')->getCurrentPlace());
    }

    public function testRejectsACardNotInPlay(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(NotEnabledTransitionException::class);

        $this->returnToHand($scenario, 'banzai');
    }
}
