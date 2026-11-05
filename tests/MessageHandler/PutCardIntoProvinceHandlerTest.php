<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Enum\StackType;
use App\Exception\Rules\CannotFillProvinceException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardSideException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PutCardIntoProvince;
use App\MessageHandler\PutCardIntoProvinceHandler;
use App\Service\ProvinceRefiller;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class PutCardIntoProvinceHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAt(string $place = 'conflict_action_window'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->hand(['ambusher' => '01-hiruma-ambusher'])
                ->dynastyDeck(['whisperer' => '01-doji-whisperer', 'scout' => '01-eager-scout'])
                ->dynastyDiscardPile(['keeper' => '01-keeper-initiate'])
                ->province('01-entrenched-position', as: 'province', dynasty: '01-ikoma-eiji', dynastyAs: 'eiji', dynastyFaceup: true)
                ->province('01-pilgrimage', as: 'other', dynasty: '01-brash-samurai', dynastyAs: 'samurai')
                ->strongholdProvince('01-shameful-display', as: 'stronghold-province')
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'], fate: 2, personalHonor: PersonalHonor::Honored))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-peace', as: 'jane-province'))
            ->persist();
    }

    private function put(Scenario $scenario, string $card, string $province, bool $faceUp): void
    {
        // the opponent sends it: any player can
        self::service(PutCardIntoProvinceHandler::class)(new PutCardIntoProvince(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card($card)->getId(),
            $scenario->card($province)->getId(),
            $faceUp,
        ));
    }

    public function testACardOfTheDynastyDeckIsPutFaceUpIntoAProvinceBesideItsCard(): void
    {
        // Walking the Way: one of the looked at cards
        $scenario = $this->scenarioAt();

        $this->put($scenario, 'whisperer', 'province', faceUp: true);

        $whisperer = $scenario->card('whisperer');
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $whisperer->getCurrentPlace());
        self::assertSame($whisperer, $scenario->card('province')->getStack()->getTopCard());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card('eiji')->getCurrentPlace());
        self::assertSame(["Doji Whisperer is put from john's dynasty deck into john's province"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testACardOfTheDiscardPileIsPutFaceDown(): void
    {
        $scenario = $this->scenarioAt();

        $this->put($scenario, 'keeper', 'province', faceUp: false);

        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $scenario->card('keeper')->getCurrentPlace());
        self::assertSame(["Keeper Initiate is put facedown from john's dynasty discard pile into john's province"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testACardMovedToAnotherProvinceLeavesItsProvinceToBeRefilled(): void
    {
        // Logistics: the province left empty is refilled once the effect is resolved
        $scenario = $this->scenarioAt();

        $this->put($scenario, 'eiji', 'other', faceUp: true);

        self::assertSame($scenario->card('other')->getStack(), $scenario->card('eiji')->getStack());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card('eiji')->getCurrentPlace());
        $this->assertRefillScheduled($scenario->card('province'));
        self::service(ProvinceRefiller::class)->refillScheduled($scenario->game());
        self::assertCount(2, $scenario->card('province')->getStack()->getCards());
    }

    public function testACardIsMovedToTheStrongholdProvince(): void
    {
        // Recalled Defenses
        $scenario = $this->scenarioAt();

        $this->put($scenario, 'samurai', 'stronghold-province', faceUp: false);

        self::assertSame($scenario->card('stronghold-province')->getStack(), $scenario->card('samurai')->getStack());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $scenario->card('samurai')->getCurrentPlace());
    }

    public function testACharacterInPlayLeavesPlayIntoAProvince(): void
    {
        // Master of Many Lifetimes: the character goes into a facedown province, its attachments leave play
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->province('01-pilgrimage', as: 'other', dynasty: '01-brash-samurai', dynastyAs: 'samurai')
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'], fate: 2, personalHonor: PersonalHonor::Honored))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-peace', as: 'jane-province'))
            ->conflict('john', 'jane-province', attackers: ['toturi'])
            ->persist();

        $this->put($scenario, 'toturi', 'other', faceUp: false);

        $toturi = $scenario->card('toturi');
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $toturi->getCurrentPlace());
        self::assertSame(Orientation::FaceDown, $toturi->getOrientation());
        self::assertSame(0, self::service(CardStateManager::class)->getState($toturi)->getFate());
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('katana')->getCurrentPlace());
        self::assertSame(11, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
        self::assertSame([], self::service(GameStateManager::class)->getState($scenario->game())->currentConflict()->attackerIds);
    }

    public function testRejectsTheProvinceTheCardIsIn(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(CannotFillProvinceException::class);

        $this->put($scenario, 'eiji', 'province', faceUp: false);
    }

    public function testRejectsAProvinceOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(NotOwnedCardException::class);

        $this->put($scenario, 'keeper', 'jane-province', faceUp: true);
    }

    public function testRejectsAConflictCard(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(WrongCardSideException::class);

        $this->put($scenario, 'ambusher', 'province', faceUp: true);
    }

    public function testRejectsACardThatIsNotAProvince(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(WrongCardTypeException::class);

        $this->put($scenario, 'keeper', 'eiji', faceUp: true);
    }

    public function testTheDeckIsNotShuffled(): void
    {
        $scenario = $this->scenarioAt();
        $scout = $scenario->card('scout');

        $this->put($scenario, 'whisperer', 'province', faceUp: false);

        self::assertSame([$scout], $scenario->player('john')->getUniqueStack(StackType::DynastyDrawDeck)->getOrderedCards());
    }
}
