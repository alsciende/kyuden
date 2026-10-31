<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CardTypes\DynastyCard;
use App\Message\DiscardCard;
use App\Message\EndResolution;
use App\Message\PlayCharacterFromProvince;
use App\Service\ProvinceRefiller;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\GameBuilder;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;

/**
 * A province a dynasty card has left is refilled once the effect is resolved, if it is still empty.
 */
class ProvinceRefillerTest extends AbstractKernelTestCase
{
    /**
     * @param (\Closure(PlayerBuilder): PlayerBuilder)|null $john more of john
     */
    private function builder(?\Closure $john = null): GameBuilder
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', function (PlayerBuilder $p) use ($john): PlayerBuilder {
                $p->fate(7)
                    ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi', dynastyFaceup: true)
                    ->dynastyDeck(['refill' => '01-doji-hotaru'])
                    ->inPlay(['scout' => '01-eager-scout']);

                return $john === null ? $p : $john($p);
            })
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['spear' => '01-master-of-the-spear']))
            ->activePlayer('john');
    }

    /**
     * The province is emptied and scheduled for a refill, as a handler does.
     */
    private function emptyProvince(Scenario $scenario): void
    {
        $toturi = $scenario->card('toturi');
        $province = $toturi->getStack();
        self::service(Registry::class)->get($toturi)->apply($toturi, 'discard_card');
        self::service(ProvinceRefiller::class)->schedule($province);
    }

    private function refilled(Scenario $scenario): bool
    {
        return $scenario->card('refill')->getStack() === $scenario->card('province')->getStack();
    }

    public function testRefillsAScheduledProvinceOnceNothingIsBeingResolved(): void
    {
        $scenario = $this->builder()->persist();
        $this->emptyProvince($scenario);

        self::service(ProvinceRefiller::class)->refillScheduled($scenario->game());

        self::assertTrue($this->refilled($scenario));
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $scenario->card('refill')->getCurrentPlace());
        self::assertSame([], self::service(GameStateManager::class)->getState($scenario->game())->provincesToRefill);
    }

    public function testWaitsForThePlayerToEndTheirAction(): void
    {
        $scenario = $this->builder(fn (PlayerBuilder $p): PlayerBuilder => $p->acted())->persist();
        $this->emptyProvince($scenario);

        self::service(ProvinceRefiller::class)->refillScheduled($scenario->game());

        self::assertFalse($this->refilled($scenario));
        $this->assertRefillScheduled($scenario->card('province'));
    }

    public function testWaitsForTheInterruptsAndReactionsToBeEnded(): void
    {
        $scenario = $this->builder(fn (PlayerBuilder $p): PlayerBuilder => $p->resolvingInterruptsAndReactions(1))->persist();
        $this->emptyProvince($scenario);

        self::service(ProvinceRefiller::class)->refillScheduled($scenario->game());

        self::assertFalse($this->refilled($scenario));
    }

    public function testWaitsForTheDuelToBeResolved(): void
    {
        $scenario = $this->builder()->duel('john', ['scout'], ['spear'])->persist();
        $this->emptyProvince($scenario);

        self::service(ProvinceRefiller::class)->refillScheduled($scenario->game());

        self::assertFalse($this->refilled($scenario));
    }

    public function testAProvinceFilledMeanwhileIsNotRefilled(): void
    {
        $scenario = $this->builder(fn (PlayerBuilder $p): PlayerBuilder => $p->dynastyDiscardPile(['academy' => '01-artisan-academy']))->persist();
        $this->emptyProvince($scenario);
        // a holding put into the province by the same effect (Rebuild)
        $academy = $scenario->card('academy');
        self::service(Registry::class)->get($academy)->apply($academy, 'put_into_province', ['stack' => $scenario->card('province')->getStack()]);

        self::service(ProvinceRefiller::class)->refillScheduled($scenario->game());

        self::assertFalse($this->refilled($scenario));
        self::assertSame([], self::service(GameStateManager::class)->getState($scenario->game())->provincesToRefill);
    }

    public function testTheProvinceOfACharacterPlayedIsRefilledOnceTheActionIsEnded(): void
    {
        $scenario = $this->builder()->persist();
        $game = $scenario->game()->getId();
        $john = $scenario->player('john')->getId();

        $this->messageBus->dispatch(new PlayCharacterFromProvince($game, $john, $scenario->card('toturi')->getId(), 5, 0, 0));
        self::assertFalse($this->refilled($scenario));

        $this->messageBus->dispatch(new EndResolution($game, $john));
        self::assertTrue($this->refilled($scenario));
    }

    public function testAProvinceEmptiedOutsideOfAnyResolutionIsRefilledAfterTheMessage(): void
    {
        $scenario = $this->builder()->persist();

        $this->messageBus->dispatch(new DiscardCard($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('toturi')->getId()));

        self::assertTrue($this->refilled($scenario));
    }

    public function testOnlyRegularProvincesAreScheduled(): void
    {
        $scenario = $this->builder()->persist();

        self::service(ProvinceRefiller::class)->schedule($scenario->card('scout')->getStack());

        self::assertSame([], self::service(GameStateManager::class)->getState($scenario->game())->provincesToRefill);
    }
}
