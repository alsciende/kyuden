<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\HiddenCardException;
use App\Message\PointAtCard;
use App\MessageHandler\PointAtCardHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class PointAtCardHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi', dynastyAs: 'facedown')
                ->hand(['banzai' => '01-banzai'])
                ->conflictDeck(['deck' => '01-banzai'])
                ->conflictDiscardPile(['katana' => '01-fine-katana'])
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->dynastyDiscardPile(['hotaru' => '01-doji-hotaru']))
            ->activePlayer('john')
            ->persist();
    }

    private function pointAt(Scenario $scenario, string $card, string $player = 'john'): void
    {
        self::service(PointAtCardHandler::class)(new PointAtCard(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testPointsAtAFaceUpCardOfAnyPlayer(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->pointAt($scenario, 'katana');
        $this->pointAt($scenario, 'hotaru');
        $this->pointAt($scenario, 'toturi', player: 'jane');

        self::assertSame([
            "john points at Fine Katana in john's conflict discard pile",
            "john points at Doji Hotaru in jane's dynasty discard pile",
            "jane points at Akodo Toturi in john's play area",
        ], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testPointsAtAFaceDownCardWithoutShowingItsFace(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->pointAt($scenario, 'facedown');
        $this->pointAt($scenario, 'province');

        self::assertSame([
            "john points at a facedown card in john's province",
            "john points at a facedown card in john's province",
        ], $this->logLines($scenario->game(), $scenario->player('jane')));
        self::assertSame([
            "john points at a facedown card in john's province",
            "john points at Entrenched Position in john's province",
        ], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testRejectsACardInHand(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(HiddenCardException::class);

        $this->pointAt($scenario, 'banzai');
    }

    public function testRejectsACardInADeck(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(HiddenCardException::class);

        $this->pointAt($scenario, 'deck');
    }
}
