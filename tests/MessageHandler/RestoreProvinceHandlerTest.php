<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Enum\Orientation;
use App\Exception\Rules\NoBrokenProvinceException;
use App\Message\RestoreProvince;
use App\MessageHandler\RestoreProvinceHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

class RestoreProvinceHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithProvinces(Orientation $janesOrientation = Orientation::UpsideDown): Scenario
    {
        return $this->scenario()
            ->atPlace('fate_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'johns-province', orientation: Orientation::FaceUp))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-peace', as: 'janes-province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi', dynastyFaceup: true, orientation: $janesOrientation))
            ->persist();
    }

    private function restore(Scenario $scenario, string $province): void
    {
        // the opponent sends it (Ethereal Alignment): any player can
        self::service(RestoreProvinceHandler::class)(new RestoreProvince(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card($province)->getId(),
        ));
    }

    public function testABrokenProvinceIsRestoredWithItsCards(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->restore($scenario, 'janes-province');

        $province = $scenario->card('janes-province');
        self::assertSame(ProvinceCard::STATE_UNBROKEN, $province->getCurrentPlace());
        self::assertSame(Orientation::FaceUp, $province->getOrientation());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card('toturi')->getCurrentPlace());
        self::assertSame(['The Art of Peace is restored'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testRejectsAnUnbrokenProvince(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(NotEnabledTransitionException::class);

        $this->restore($scenario, 'johns-province');
    }

    public function testRejectsRestoringWhileNoProvinceIsBroken(): void
    {
        $scenario = $this->scenarioWithProvinces(Orientation::FaceUp);

        $this->expectException(NoBrokenProvinceException::class);

        $this->restore($scenario, 'janes-province');
    }
}
