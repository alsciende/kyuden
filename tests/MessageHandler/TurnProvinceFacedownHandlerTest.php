<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Enum\Orientation;
use App\Exception\Rules\NoFaceupProvinceException;
use App\Exception\Rules\WrongCardSideException;
use App\Message\TurnProvinceFacedown;
use App\MessageHandler\TurnProvinceFacedownHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

class TurnProvinceFacedownHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithProvinces(Orientation $janesOrientation = Orientation::FaceUp): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'johns-province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi', dynastyFaceup: true, orientation: Orientation::FaceUp))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-peace', as: 'janes-province', orientation: $janesOrientation))
            ->persist();
    }

    private function turn(Scenario $scenario, string $card): void
    {
        // the opponent sends it: any player can
        self::service(TurnProvinceFacedownHandler::class)(new TurnProvinceFacedown(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testAFaceupProvinceIsTurnedFacedownWithItsCards(): void
    {
        // Kitsuki Yaruma
        $scenario = $this->scenarioWithProvinces();
        $id = $scenario->card('johns-province')->getId();

        $this->turn($scenario, 'johns-province');

        $province = $scenario->card('johns-province');
        self::assertSame(ProvinceCard::STATE_FACEDOWN, $province->getCurrentPlace());
        self::assertSame(Orientation::FaceDown, $province->getOrientation());
        // the players have seen it: it keeps its id, and the card on it stays as it was
        self::assertSame($id, $province->getId());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card('toturi')->getCurrentPlace());
        self::assertSame(['Entrenched Position is turned facedown'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testAProvinceOfTheOpponentCanBeTurnedFacedown(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->turn($scenario, 'janes-province');

        self::assertSame(ProvinceCard::STATE_FACEDOWN, $scenario->card('janes-province')->getCurrentPlace());
    }

    public function testRejectsABrokenProvince(): void
    {
        $scenario = $this->scenarioWithProvinces(Orientation::UpsideDown);

        $this->expectException(NotEnabledTransitionException::class);

        $this->turn($scenario, 'janes-province');
    }

    public function testRejectsAFacedownProvince(): void
    {
        $scenario = $this->scenarioWithProvinces(Orientation::FaceDown);

        $this->expectException(NotEnabledTransitionException::class);

        $this->turn($scenario, 'janes-province');
    }

    public function testRejectsADynastyCard(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(WrongCardSideException::class);

        $this->turn($scenario, 'toturi');
    }

    public function testUnavailableOnceNoProvinceIsUnbrokenAndFaceup(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', orientation: Orientation::UpsideDown))
            ->build();

        $this->expectException(NoFaceupProvinceException::class);

        self::service(TurnProvinceFacedownHandler::class)->assertAvailable($scenario->game(), $scenario->player('john'));
    }
}
