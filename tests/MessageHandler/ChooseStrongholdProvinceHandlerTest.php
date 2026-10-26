<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\DataFixtures\AppFixtures;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\StrongholdProvinceAlreadyChosenException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\ChooseStrongholdProvince;
use App\MessageHandler\ChooseStrongholdProvinceHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class ChooseStrongholdProvinceHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAtChooseStronghold(): Scenario
    {
        return $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::LION_STARTER_DECK, [
                'ancestral-lands' => '01-ancestral-lands',
                'banzai' => '01-banzai',
            ]))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRANE_STARTER_DECK, [
                'entrenched-position' => '01-entrenched-position',
            ]))
            ->persist();
    }

    private function choose(Scenario $scenario, string $player, string $card): ChooseStrongholdProvince
    {
        return new ChooseStrongholdProvince(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
        );
    }

    public function testRecordsTheChosenProvince(): void
    {
        $scenario = $this->scenarioAtChooseStronghold();

        self::service(ChooseStrongholdProvinceHandler::class)($this->choose($scenario, 'john', 'ancestral-lands'));

        self::assertSame(
            $scenario->card('ancestral-lands')->getId(),
            $this->playerStateManager->getState($scenario->player('john'))->getStrongholdProvinceId()
        );
    }

    public function testRejectsACardThatIsNotAProvince(): void
    {
        $scenario = $this->scenarioAtChooseStronghold();

        $this->expectException(WrongCardTypeException::class);

        self::service(ChooseStrongholdProvinceHandler::class)($this->choose($scenario, 'john', 'banzai'));
    }

    public function testRejectsAProvinceOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioAtChooseStronghold();

        $this->expectException(NotOwnedCardException::class);

        self::service(ChooseStrongholdProvinceHandler::class)($this->choose($scenario, 'john', 'entrenched-position'));
    }

    public function testRejectsASecondChoice(): void
    {
        $scenario = $this->scenarioAtChooseStronghold();
        $handler = self::service(ChooseStrongholdProvinceHandler::class);
        $handler($this->choose($scenario, 'john', 'ancestral-lands'));

        $this->expectException(StrongholdProvinceAlreadyChosenException::class);

        $handler($this->choose($scenario, 'john', 'ancestral-lands'));
    }

    public function testGameIsSetUpOnceTheLastPlayerHasChosen(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fromStarterDeck(AppFixtures::LION_STARTER_DECK, [
                    'ancestral-lands' => '01-ancestral-lands',
                ])
                ->chooseStrongholdProvince('ancestral-lands'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRANE_STARTER_DECK, [
                'entrenched-position' => '01-entrenched-position',
            ]))
            ->persist();

        // through the bus, so that automatic transitions happen
        $this->messageBus->dispatch($this->choose($scenario, 'jane', 'entrenched-position'));

        self::assertSame('reveal_facedown_dynasty_cards', $scenario->game()->getCurrentPlace());
    }
}
