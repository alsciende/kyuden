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

class DiscardCardHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(): Scenario
    {
        return $this->scenario()
            ->atPlace('react_to_broken_province')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '01-akodo-toturi', dynastyAs: 'toturi')
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
