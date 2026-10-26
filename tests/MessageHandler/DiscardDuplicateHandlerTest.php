<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Enum\Orientation;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotADuplicateException;
use App\Exception\Rules\NotInPlayException;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\DiscardDuplicate;
use App\MessageHandler\DiscardDuplicateHandler;
use App\State\Manager\CardStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class DiscardDuplicateHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithDuplicates(string $place = 'play_cards_from_provinces'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi'], fate: 1)
                ->inPlay(['kazue' => '38-togashi-kazue'])
                ->inPlay(['berserker' => '01-matsu-berserker'])
                ->province('01-entrenched-position', dynasty: '16-akodo-toturi', dynastyAs: 'toturi-duplicate', dynastyFaceup: true)
                ->province('01-entrenched-position', dynasty: '01-akodo-toturi', dynastyAs: 'facedown-toturi')
                ->province('01-entrenched-position', dynasty: '01-matsu-berserker', dynastyAs: 'berserker-duplicate', dynastyFaceup: true)
                ->hand(['kazue-duplicate' => '01-togashi-kazue'])
                ->dynastyDeck(['refill' => '01-doji-hotaru']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['jane-toturi' => '01-akodo-toturi']))
            ->activePlayer('john')
            ->persist();
    }

    private function discard(Scenario $scenario, string $card, string $copy, string $player = 'john'): void
    {
        self::service(DiscardDuplicateHandler::class)(new DiscardDuplicate(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
            $scenario->card($copy)->getId(),
        ));
    }

    public function testTheDuplicateIsDiscardedAndOneFateIsPlacedOnTheCopy(): void
    {
        $scenario = $this->scenarioWithDuplicates();

        $this->discard($scenario, 'toturi-duplicate', 'toturi');

        self::assertSame(DynastyCard::STATE_DISCARD_PILE, $scenario->card('toturi-duplicate')->getCurrentPlace());
        self::assertSame(2, self::service(CardStateManager::class)->getState($scenario->card('toturi'))->getFate());
    }

    public function testTheProvinceIsRefilled(): void
    {
        $scenario = $this->scenarioWithDuplicates();
        $province = $scenario->card('toturi-duplicate')->getStack();

        $this->discard($scenario, 'toturi-duplicate', 'toturi');

        self::assertSame($province, $scenario->card('refill')->getStack());
        self::assertSame(Orientation::FaceDown, $scenario->card('refill')->getOrientation());
    }

    public function testADuplicateCanBeDiscardedFromHand(): void
    {
        $scenario = $this->scenarioWithDuplicates();

        // another version of the card, from the other side
        $this->discard($scenario, 'kazue-duplicate', 'kazue');

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('kazue-duplicate')->getCurrentPlace());
        self::assertSame(1, self::service(CardStateManager::class)->getState($scenario->card('kazue'))->getFate());
    }

    public function testThePlayerKeepsTheOpportunityToAct(): void
    {
        $scenario = $this->scenarioWithDuplicates();

        $this->discard($scenario, 'toturi-duplicate', 'toturi');

        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->hasActed());
    }

    public function testRejectsACharacterThatIsNotUnique(): void
    {
        $scenario = $this->scenarioWithDuplicates();

        $this->expectException(NotADuplicateException::class);

        $this->discard($scenario, 'berserker-duplicate', 'berserker');
    }

    public function testRejectsACharacterWithAnotherTitle(): void
    {
        $scenario = $this->scenarioWithDuplicates();

        $this->expectException(NotADuplicateException::class);

        $this->discard($scenario, 'kazue-duplicate', 'toturi');
    }

    public function testRejectsAFacedownCard(): void
    {
        $scenario = $this->scenarioWithDuplicates();

        $this->expectException(NotADuplicateException::class);

        $this->discard($scenario, 'facedown-toturi', 'toturi');
    }

    public function testRejectsACopyOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioWithDuplicates();

        $this->expectException(NotOwnedCardException::class);

        $this->discard($scenario, 'toturi-duplicate', 'jane-toturi');
    }

    public function testRejectsACopyThatIsNotInPlay(): void
    {
        $scenario = $this->scenarioWithDuplicates();

        $this->expectException(NotInPlayException::class);

        $this->discard($scenario, 'toturi-duplicate', 'facedown-toturi');
    }

    public function testRejectsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->scenarioWithDuplicates();

        $this->expectException(NotActivePlayerException::class);

        $this->discard($scenario, 'jane-toturi', 'jane-toturi', player: 'jane');
    }

    public function testRejectedOutsideOfThePlayCardsFromProvincesStep(): void
    {
        $scenario = $this->scenarioWithDuplicates('conflict_action_window');

        $this->expectException(WorkflowException::class);

        $this->discard($scenario, 'toturi-duplicate', 'toturi');
    }
}
