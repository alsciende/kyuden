<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Exception\Rules\WrongCardSideException;
use App\Message\RevealDynastyCard;
use App\MessageHandler\RevealDynastyCardHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class RevealDynastyCardHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithAFacedownDynastyCard(): Scenario
    {
        return $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi'))
            ->withPlayer('jane')
            ->persist();
    }

    private function reveal(Scenario $scenario, string $card): void
    {
        self::service(RevealDynastyCardHandler::class)(new RevealDynastyCard(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testAFacedownDynastyCardInAProvinceIsTurnedFaceup(): void
    {
        $scenario = $this->scenarioWithAFacedownDynastyCard();

        $this->reveal($scenario, 'toturi');

        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card('toturi')->getCurrentPlace());
    }

    public function testAProvinceIsNotADynastyCard(): void
    {
        $scenario = $this->scenarioWithAFacedownDynastyCard();

        $this->expectException(WrongCardSideException::class);

        $this->reveal($scenario, 'province');
    }
}
