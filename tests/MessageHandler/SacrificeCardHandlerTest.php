<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Message\SacrificeCard;
use App\MessageHandler\SacrificeCardHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Exception\UndefinedTransitionException;

class SacrificeCardHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('01-yojin-no-shiro', as: 'stronghold')
                ->province('01-entrenched-position', as: 'province', dynasty: '01-artisan-academy', dynastyAs: 'academy', dynastyFaceup: true)
                ->province('01-the-art-of-peace', dynasty: '01-doji-hotaru', dynastyAs: 'hotaru', dynastyFaceup: true)
                ->dynastyDeck(['refill' => '01-doji-whisperer'])
                ->hand(['banzai' => '01-banzai'])
                ->inPlay([
                    'toturi' => '01-akodo-toturi',
                    'katana' => '01-fine-katana',
                ]))
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->persist();
    }

    private function sacrifice(Scenario $scenario, string $card): void
    {
        self::service(SacrificeCardHandler::class)(new SacrificeCard(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testAnyPlayerSacrificesACharacterInPlay(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->sacrifice($scenario, 'toturi');

        self::assertSame(DynastyCard::STATE_DISCARD_PILE, $scenario->card('toturi')->getCurrentPlace());
    }

    public function testSacrificesAnAttachment(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->sacrifice($scenario, 'katana');

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('katana')->getCurrentPlace());
    }

    public function testSacrificesAHoldingInAProvinceAndRefillsIt(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->sacrifice($scenario, 'academy');

        self::assertSame(DynastyCard::STATE_DISCARD_PILE, $scenario->card('academy')->getCurrentPlace());
        self::assertSame($scenario->card('province')->getStack(), $scenario->card('refill')->getStack());
    }

    public function testRejectsACharacterInAProvince(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(NotEnabledTransitionException::class);

        $this->sacrifice($scenario, 'hotaru');
    }

    public function testRejectsACardInHand(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(NotEnabledTransitionException::class);

        $this->sacrifice($scenario, 'banzai');
    }

    public function testRejectsAStronghold(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(UndefinedTransitionException::class);

        $this->sacrifice($scenario, 'stronghold');
    }
}
