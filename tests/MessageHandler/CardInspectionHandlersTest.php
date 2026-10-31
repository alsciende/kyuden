<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\HiddenPile;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Message\LookAtCard;
use App\Message\LookAtCards;
use App\Message\RevealCard;
use App\Message\RevealCards;
use App\MessageHandler\LookAtCardHandler;
use App\MessageHandler\LookAtCardsHandler;
use App\MessageHandler\RevealCardHandler;
use App\MessageHandler\RevealCardsHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * Looking at cards and revealing them: nothing changes but the log, which gives the cards to the players who learn
 * them.
 */
class CardInspectionHandlersTest extends AbstractKernelTestCase
{
    private function scenarioWithDecks(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                // from the bottom to the top
                ->conflictDeck(['01-banzai', '01-fine-katana', '01-ornate-fan'])
                ->hand(['01-outwit']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['01-way-of-the-crane', '01-assassination'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceDown))
            ->persist();
    }

    public function testThePlayerLooksAtTheTopCardsOfTheirDeckAlone(): void
    {
        $scenario = $this->scenarioWithDecks();

        self::service(LookAtCardsHandler::class)(new LookAtCards($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('john')->getId(), HiddenPile::ConflictDeck, 2));

        self::assertSame(["john looks at Ornate Fan, Fine Katana in john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('john')));
        self::assertSame(["john looks at a hidden card, a hidden card in john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testThePlayerLooksAtTheWholeHandOfTheOpponentWhoKnowsItAlready(): void
    {
        $scenario = $this->scenarioWithDecks();

        self::service(LookAtCardsHandler::class)(new LookAtCards($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('jane')->getId(), HiddenPile::Hand));

        $line = "john looks at Way of the Crane, Assassination in jane's hand";
        self::assertSame([$line], $this->logLines($scenario->game(), $scenario->player('john')));
        self::assertSame([$line], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testThePlayerRevealsTheTopCardsOfADeckToEveryPlayer(): void
    {
        $scenario = $this->scenarioWithDecks();

        self::service(RevealCardsHandler::class)(new RevealCards($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->player('john')->getId(), HiddenPile::ConflictDeck, 5));

        self::assertSame(["jane reveals Ornate Fan, Fine Katana, Banzai! from john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('jane')));
        self::assertSame(["jane reveals Ornate Fan, Fine Katana, Banzai! from john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testThePlayerLooksAtAFacedownProvinceOfTheOpponent(): void
    {
        $scenario = $this->scenarioWithDecks();

        self::service(LookAtCardHandler::class)(new LookAtCard($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('province')->getId()));

        self::assertSame(["john looks at Entrenched Position in jane's province"], $this->logLines($scenario->game(), $scenario->player('john')));
        // the owner of a province knows it
        self::assertSame(["john looks at Entrenched Position in jane's province"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testThePlayerRevealsACardOfTheirHand(): void
    {
        $scenario = $this->scenarioWithDecks();
        $card = $scenario->player('john')->getUniqueStack(StackType::Hand)->getTopCard();
        self::assertNotNull($card);

        self::service(RevealCardHandler::class)(new RevealCard($scenario->game()->getId(), $scenario->player('john')->getId(), $card->getId()));

        self::assertSame(["john reveals Outwit from john's hand"], $this->logLines($scenario->game(), $scenario->player('jane')));
        self::assertSame(Orientation::FaceUp, $card->getOrientation());
    }
}
