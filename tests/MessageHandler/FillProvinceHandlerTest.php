<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Exception\Data\WorkflowException;
use App\Message\FillProvince;
use App\MessageHandler\FillProvinceHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class FillProvinceHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAt(string $place): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province')
                ->dynastyDeck(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->persist();
    }

    private function fill(Scenario $scenario): void
    {
        self::service(FillProvinceHandler::class)(new FillProvince(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('province')->getId(),
        ));
    }

    public function testAPlayerWhoIsNotActiveRefillsTheirProvinceDuringThePlayCardsFromProvincesStep(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        $this->fill($scenario);

        $toturi = $scenario->card('toturi');
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $toturi->getCurrentPlace());
        self::assertSame($scenario->card('province')->getStack(), $toturi->getStack());
    }

    public function testRejectsARefillInAnotherStep(): void
    {
        $scenario = $this->scenarioAt('collect_fate');

        $this->expectException(WorkflowException::class);

        $this->fill($scenario);
    }
}
