<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Orientation;
use App\Exception\Rules\NotEnoughHonorTokensException;
use App\Exception\Rules\NotInProvinceException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PlaceHonorTokens;
use App\Message\RemoveHonorTokens;
use App\Message\SacrificeCard;
use App\Message\SwitchProvinceCards;
use App\MessageHandler\PlaceHonorTokensHandler;
use App\MessageHandler\RemoveHonorTokensHandler;
use App\MessageHandler\SacrificeCardHandler;
use App\MessageHandler\SwitchProvinceCardsHandler;
use App\State\Manager\CardStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * Honor tokens (counters, not status tokens) on provinces and holdings: placed and removed in any place, by any
 * player, on any player's cards; those of a holding return to the token pool when it leaves play.
 */
class HonorTokenHandlersTest extends AbstractKernelTestCase
{
    private function scenarioWithProvinces(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', dynasty: '01-artisan-academy', dynastyAs: 'academy', dynastyFaceup: true)
                ->province('01-the-art-of-peace', dynasty: '01-doji-hotaru', dynastyAs: 'hotaru', dynastyFaceup: true)
                ->province('01-pilgrimage', dynasty: '01-imperial-storehouse', dynastyAs: 'storehouse')
                ->dynastyDeck(['01-doji-whisperer'])
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'janes-province', orientation: Orientation::FaceUp))
            ->persist();
    }

    private function honorTokens(Scenario $scenario, string $card): int
    {
        return self::service(CardStateManager::class)->getState($scenario->card($card))->getHonorTokens();
    }

    private function place(Scenario $scenario, string $card, int $amount): void
    {
        self::service(PlaceHonorTokensHandler::class)(new PlaceHonorTokens($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card($card)->getId(), $amount));
    }

    private function remove(Scenario $scenario, string $card, int $amount): void
    {
        self::service(RemoveHonorTokensHandler::class)(new RemoveHonorTokens($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card($card)->getId(), $amount));
    }

    public function testPlacesHonorTokensOnAProvinceOfAnyPlayer(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->place($scenario, 'janes-province', 1);
        $this->place($scenario, 'janes-province', 2);

        self::assertSame(3, $this->honorTokens($scenario, 'janes-province'));
    }

    public function testPlacesHonorTokensOnAHoldingInAProvince(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->place($scenario, 'academy', 1);

        self::assertSame(1, $this->honorTokens($scenario, 'academy'));
    }

    public function testRejectsACharacter(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(WrongCardTypeException::class);

        $this->place($scenario, 'toturi', 1);
    }

    public function testRejectsACharacterInAProvince(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(WrongCardTypeException::class);

        $this->place($scenario, 'hotaru', 1);
    }

    public function testRejectsAFacedownHolding(): void
    {
        $scenario = $this->scenarioWithProvinces();

        $this->expectException(NotInProvinceException::class);

        $this->place($scenario, 'storehouse', 1);
    }

    public function testRemovesHonorTokens(): void
    {
        $scenario = $this->scenarioWithProvinces();
        $this->place($scenario, 'province', 3);

        $this->remove($scenario, 'province', 2);

        self::assertSame(1, $this->honorTokens($scenario, 'province'));
    }

    public function testRejectsRemovingMoreHonorTokensThanTheCardHolds(): void
    {
        $scenario = $this->scenarioWithProvinces();
        $this->place($scenario, 'province', 1);

        $this->expectException(NotEnoughHonorTokensException::class);

        $this->remove($scenario, 'province', 2);
    }

    public function testTheHonorTokensOfAHoldingLeavingPlayReturnToTheTokenPool(): void
    {
        $scenario = $this->scenarioWithProvinces();
        $this->place($scenario, 'academy', 2);

        self::service(SacrificeCardHandler::class)(new SacrificeCard($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('academy')->getId()));

        self::assertSame(0, $this->honorTokens($scenario, 'academy'));
    }

    public function testAHoldingMovedToAnotherProvinceKeepsItsHonorTokens(): void
    {
        $scenario = $this->scenarioWithProvinces();
        $this->place($scenario, 'academy', 2);

        self::service(SwitchProvinceCardsHandler::class)(new SwitchProvinceCards($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('academy')->getId(), $scenario->card('hotaru')->getId()));

        self::assertSame(2, $this->honorTokens($scenario, 'academy'));
    }
}
