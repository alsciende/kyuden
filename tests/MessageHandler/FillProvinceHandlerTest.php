<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Exception\Rules\CannotFillProvinceException;
use App\Exception\Rules\WrongCardTypeException;
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

    private function fill(Scenario $scenario, string $sender = 'john'): void
    {
        self::service(FillProvinceHandler::class)(new FillProvince(
            $scenario->game()->getId(),
            $scenario->player($sender)->getId(),
            $scenario->card('province')->getId(),
        ));
    }

    public function testAPlayerWhoIsNotActiveRefillsTheirProvinceDuringThePlayCardsFromProvincesStep(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        $this->fill($scenario);

        // the discard pile is shuffled into a new deck, which creates the card again
        $toturi = $scenario->card('province')->getStack()->getTopCard();
        self::assertInstanceOf(DynastyCard::class, $toturi);
        self::assertSame('01-akodo-toturi', $toturi->getLogicalCard()->getId());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $toturi->getCurrentPlace());
    }

    public function testAProvinceIsFilledFromTheDeckOfItsOwner(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        $this->fill($scenario, sender: 'jane');

        self::assertSame($scenario->card('province')->getStack(), $scenario->card('toturi')->getStack());
    }

    public function testRefillsFromTheDiscardPileWhenTheDynastyDeckIsEmpty(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->province('01-entrenched-position', as: 'province')
                ->dynastyDiscardPile(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane')
            ->persist();

        $this->fill($scenario);

        // the discard pile is shuffled into a new deck, which creates the card again
        $toturi = $scenario->card('province')->getStack()->getTopCard();
        self::assertInstanceOf(DynastyCard::class, $toturi);
        self::assertSame('01-akodo-toturi', $toturi->getLogicalCard()->getId());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $toturi->getCurrentPlace());
        self::assertSame(5, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testAProvinceIsRefilledInAnyStep(): void
    {
        $scenario = $this->scenarioAt('react_to_broken_province');

        $this->fill($scenario);

        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $scenario->card('toturi')->getCurrentPlace());
    }

    public function testRejectsAProvinceWithADynastyCardOnIt(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi')
                ->dynastyDeck(['01-doji-hotaru']))
            ->withPlayer('jane')
            ->persist();

        $this->expectException(CannotFillProvinceException::class);

        $this->fill($scenario);
    }

    public function testRejectsTheStrongholdProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->strongholdProvince('01-entrenched-position', as: 'province')
                ->dynastyDeck(['01-doji-hotaru']))
            ->withPlayer('jane')
            ->persist();

        $this->expectException(CannotFillProvinceException::class);

        $this->fill($scenario);
    }

    public function testRejectsACardThatIsNotAProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['province' => '01-akodo-toturi'])
                ->dynastyDeck(['01-doji-hotaru']))
            ->withPlayer('jane')
            ->persist();

        $this->expectException(WrongCardTypeException::class);

        $this->fill($scenario);
    }
}
