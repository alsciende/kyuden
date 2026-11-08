<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Enum\Orientation;
use App\Exception\Rules\CannotFillProvinceException;
use App\Exception\Rules\NotInProvinceException;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\RedistributeProvinceCards;
use App\MessageHandler\RedistributeProvinceCardsHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class RedistributeProvinceCardsHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithProvinces(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'johns-province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-peace', as: 'first', dynasty: '01-doji-whisperer', dynastyAs: 'whisperer', dynastyFaceup: true, orientation: Orientation::FaceUp)
                ->province('01-pilgrimage', as: 'second', dynasty: '01-brash-samurai', dynastyAs: 'samurai')
                ->province('01-night-raid', as: 'third', dynasty: '01-ikoma-eiji', dynastyAs: 'eiji')
                ->strongholdProvince('01-shameful-display', as: 'stronghold-province'))
            ->persist();
    }

    /**
     * @param array<string, string> $placements province alias by card alias
     */
    private function redistribute(Scenario $scenario, array $placements): void
    {
        // Governor's Spy: john redistributes the cards of jane's provinces
        self::service(RedistributeProvinceCardsHandler::class)(new RedistributeProvinceCards(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->player('jane')->getId(),
            array_map(fn (string $card): string => $scenario->card($card)->getId(), array_keys($placements)),
            array_map(fn (string $province): string => $scenario->card($province)->getId(), array_values($placements)),
        ));
    }

    /**
     * @return list<string> the codes of the dynasty cards of the province, from the bottom, each face down
     */
    private function dynastyCodes(Scenario $scenario, string $province): array
    {
        $cards = array_values(array_filter($scenario->card($province)->getStack()->getOrderedCards(), fn (PhysicalCard $card): bool => $card instanceof DynastyCard));
        foreach ($cards as $card) {
            self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $card->getCurrentPlace());
        }

        return array_map(fn (PhysicalCard $card): string => $card->getLogicalCard()->getId(), $cards);
    }

    public function testTheCardsArePutBackFacedownWithNewIds(): void
    {
        $scenario = $this->scenarioWithProvinces();
        $ids = array_map(fn (string $card): string => $scenario->card($card)->getId(), ['whisperer', 'samurai', 'eiji']);

        $this->redistribute($scenario, ['whisperer' => 'second', 'samurai' => 'first', 'eiji' => 'first']);

        self::assertSame(['01-brash-samurai', '01-ikoma-eiji'], $this->dynastyCodes($scenario, 'first'));
        self::assertSame(['01-doji-whisperer'], $this->dynastyCodes($scenario, 'second'));
        self::assertSame([], $this->dynastyCodes($scenario, 'third'));
        // nobody can follow a card: the cards of the provinces all have new ids
        $cardIds = array_map(fn (PhysicalCard $card): string => $card->getId(), $scenario->game()->getPhysicalCards()->getValues());
        self::assertSame([], array_intersect($ids, $cardIds));
        // the province card stays at the bottom of its province
        self::assertSame($scenario->card('first'), $scenario->card('first')->getStack()->getOrderedCards()[0]);
        // the province left empty is refilled once the effect is resolved
        $this->assertRefillScheduled($scenario->card('third'));
        self::assertSame([
            "Doji Whisperer is turned facedown in jane's province",
            'john redistributes the cards in the provinces of jane',
        ], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testACardLeftInItsProvinceGetsANewIdToo(): void
    {
        $scenario = $this->scenarioWithProvinces();
        $id = $scenario->card('samurai')->getId();

        $this->redistribute($scenario, ['samurai' => 'second', 'eiji' => 'third']);

        self::assertSame(['01-brash-samurai'], $this->dynastyCodes($scenario, 'second'));
        self::assertNotContains($id, array_map(fn (PhysicalCard $card): string => $card->getId(), $scenario->card('second')->getStack()->getCards()->getValues()));
        // a card not listed stays as it was
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card('whisperer')->getCurrentPlace());
    }

    public function testRejectsACardOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(NotOwnedCardException::class);

        $this->redistribute($scenario, ['toturi' => 'first']);
    }

    public function testRejectsAProvinceOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(NotOwnedCardException::class);

        $this->redistribute($scenario, ['samurai' => 'johns-province']);
    }

    public function testRejectsTheStrongholdProvince(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(CannotFillProvinceException::class);

        $this->redistribute($scenario, ['samurai' => 'stronghold-province']);
    }

    public function testRejectsAProvinceAsACard(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(NotInProvinceException::class);

        $this->redistribute($scenario, ['second' => 'first']);
    }

    public function testRejectsACardWithoutProvince(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(\InvalidArgumentException::class);

        self::service(RedistributeProvinceCardsHandler::class)(new RedistributeProvinceCards(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->player('jane')->getId(),
            [$scenario->card('samurai')->getId(), $scenario->card('eiji')->getId()],
            [$scenario->card('first')->getId()],
        ));
    }
}
