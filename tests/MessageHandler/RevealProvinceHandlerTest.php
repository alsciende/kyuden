<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Enum\Orientation;
use App\Exception\Rules\NoFacedownProvinceException;
use App\Exception\Rules\WrongCardSideException;
use App\Message\RevealProvince;
use App\MessageHandler\RevealProvinceHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

class RevealProvinceHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithProvinces(Orientation $janesOrientation = Orientation::FaceDown): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'johns-province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'janes-province', orientation: $janesOrientation))
            ->persist();
    }

    private function reveal(Scenario $scenario, string $card): void
    {
        self::service(RevealProvinceHandler::class)(new RevealProvince(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testAFacedownProvinceIsTurnedFaceup(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->reveal($scenario, 'johns-province');

        self::assertSame(ProvinceCard::STATE_UNBROKEN, $scenario->card('johns-province')->getCurrentPlace());
    }

    public function testAProvinceOfTheOpponentCanBeRevealed(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->reveal($scenario, 'janes-province');

        self::assertSame(ProvinceCard::STATE_UNBROKEN, $scenario->card('janes-province')->getCurrentPlace());
    }

    public function testRejectsAProvinceAlreadyFaceup(): void
    {
        $scenario = $this->scenarioWithProvinces(Orientation::FaceUp);

        $this->expectException(NotEnabledTransitionException::class);

        $this->reveal($scenario, 'janes-province');
    }

    public function testRejectsADynastyCard(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(WrongCardSideException::class);

        $this->reveal($scenario, 'toturi');
    }

    public function testUnavailableOnceNoProvinceIsFacedown(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', orientation: Orientation::FaceUp))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', orientation: Orientation::UpsideDown))
            ->build();

        $this->expectException(NoFacedownProvinceException::class);

        self::service(RevealProvinceHandler::class)->assertAvailable($scenario->game(), $scenario->player('john'));
    }
}
