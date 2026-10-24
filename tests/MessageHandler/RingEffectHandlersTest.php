<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Enum\PersonalHonor;
use App\Enum\StackType;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\DiscardRandomCardFromHand;
use App\Message\DishonorCharacter;
use App\Message\HonorCharacter;
use App\Message\PlaceFate;
use App\Message\RemoveFate;
use App\MessageHandler\DiscardRandomCardFromHandHandler;
use App\MessageHandler\DishonorCharacterHandler;
use App\MessageHandler\HonorCharacterHandler;
use App\MessageHandler\PlaceFateHandler;
use App\MessageHandler\RemoveFateHandler;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * Effect messages used to resolve ring effects: accepted in any place, from any player, on any player's cards.
 */
class RingEffectHandlersTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(): Scenario
    {
        return $this->scenario()
            ->atPlace('resolve_ring_effects')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi'], fate: 2)
                ->hand(['katana' => '01-fine-katana']))
            ->persist();
    }

    private function cardState(Scenario $scenario, string $card): CardState
    {
        return self::service(CardStateManager::class)->getState($scenario->card($card));
    }

    public function testAPlayerHonorsAnOpponentsCharacter(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(HonorCharacterHandler::class)(new HonorCharacter($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('toturi')->getId()));

        self::assertSame(PersonalHonor::Honored, $this->cardState($scenario, 'toturi')->getPersonalHonor());
    }

    public function testAPlayerDishonorsAnOpponentsCharacter(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(DishonorCharacterHandler::class)(new DishonorCharacter($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('toturi')->getId()));

        self::assertSame(PersonalHonor::Dishonored, $this->cardState($scenario, 'toturi')->getPersonalHonor());
    }

    public function testOnlyACharacterCanBeHonored(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(WrongCardTypeException::class);

        self::service(HonorCharacterHandler::class)(new HonorCharacter($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('katana')->getId()));
    }

    public function testAPlayerRemovesFateFromAnOpponentsCharacter(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(RemoveFateHandler::class)(new RemoveFate($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('toturi')->getId(), 1));

        self::assertSame(1, $this->cardState($scenario, 'toturi')->getFate());
    }

    public function testAPlayerPlacesFateOnAnOpponentsCharacter(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(PlaceFateHandler::class)(new PlaceFate($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('toturi')->getId(), 1));

        self::assertSame(3, $this->cardState($scenario, 'toturi')->getFate());
    }

    public function testFateIsOnlyPlacedOnACharacterInPlay(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(WrongCardTypeException::class);

        self::service(PlaceFateHandler::class)(new PlaceFate($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('katana')->getId(), 1));
    }

    public function testAPlayerDiscardsARandomCardFromTheHandOfAnOpponent(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(DiscardRandomCardFromHandHandler::class)(new DiscardRandomCardFromHand($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('jane')->getId()));

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('katana')->getCurrentPlace());
    }

    public function testAPlayerDiscardsSeveralRandomCards(): void
    {
        $scenario = $this->scenario()
            ->atPlace('resolve_ring_effects')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['katana' => '01-fine-katana', 'banzai' => '01-banzai', 'ambusher' => '01-hiruma-ambusher']))
            ->persist();

        self::service(DiscardRandomCardFromHandHandler::class)(new DiscardRandomCardFromHand($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('jane')->getId(), 2));

        self::assertCount(1, $scenario->player('jane')->getUniqueStack(StackType::Hand)->getCards());
        self::assertCount(2, $scenario->player('jane')->getUniqueStack(StackType::ConflictDiscardPile)->getCards());
    }

    public function testTheWholeHandIsDiscardedWhenItHoldsFewerCards(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(DiscardRandomCardFromHandHandler::class)(new DiscardRandomCardFromHand($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('jane')->getId(), 3));

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('katana')->getCurrentPlace());
        self::assertCount(0, $scenario->player('jane')->getUniqueStack(StackType::Hand)->getCards());
    }

    public function testNothingHappensWhenTheHandIsEmpty(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(DiscardRandomCardFromHandHandler::class)(new DiscardRandomCardFromHand($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->player('john')->getId()));

        self::assertCount(0, $scenario->player('john')->getUniqueStack(StackType::ConflictDiscardPile)->getCards());
    }
}
