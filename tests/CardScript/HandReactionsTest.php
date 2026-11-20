<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\HiddenPile;
use App\Event\CardsDiscardedFromHandEvent;
use App\Event\CardsLookedAtEvent;
use App\Event\CardsRevealedEvent;
use App\Message\DiscardCard;
use App\Message\DiscardRandomCardFromHand;
use App\Message\LookAtCards;
use App\Message\RevealCards;
use App\MessageHandler\DiscardCardHandler;
use App\MessageHandler\DiscardRandomCardFromHandHandler;
use App\MessageHandler\LookAtCardsHandler;
use App\MessageHandler\RevealCardsHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to the hand: cards discarded from it, looked at or revealed.
 */
class HandReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['hyobu', 'motivator', 'warrior', 'yoriki', 'janes_warrior'];

    /**
     * @return list<string> the cards offered to the player, by alias, among those of ALIASES
     */
    private function offered(Scenario $scenario, string $player): array
    {
        $cardIds = array_merge(...array_map(
            fn (ReactionWindow $window): array => $window->cardIds,
            self::service(PlayerStateManager::class)->getState($scenario->player($player))->getReactionWindows(),
        ));

        return array_values(array_filter(self::ALIASES, fn (string $alias): bool => in_array($scenario->card($alias)->getId(), $cardIds, true)));
    }

    /**
     * @param class-string $event
     *
     * @return list<string>
     */
    private function subscribers(string $event): array
    {
        $registry = self::service(CardScriptRegistry::class);

        return array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists($event, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));
    }

    private function aScenario(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['hyobu' => '17-shosuro-hyobu'])
                ->inPlay(['motivator' => '27-akodo-motivator'])
                ->inPlay(['warrior' => '36-keen-warrior'])
                ->inPlay(['yoriki' => '42-discerning-yoriki'])
                ->hand(['johns_card' => '01-banzai']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['janes_warrior' => '36-keen-warrior'])
                ->hand(['janes_card' => '01-banzai', 'other_card' => '01-banzai']))
            ->persist();
    }

    public function testEveryCardReactingToTheHandSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing(['17-shosuro-hyobu', '27-akodo-motivator'], $this->subscribers(CardsDiscardedFromHandEvent::class));
        self::assertEqualsCanonicalizing(['36-keen-warrior', '42-discerning-yoriki'], $this->subscribers(CardsLookedAtEvent::class));
        self::assertEqualsCanonicalizing(['36-keen-warrior', '42-discerning-yoriki'], $this->subscribers(CardsRevealedEvent::class));
    }

    public function testCardsDiscardedFromAHand(): void
    {
        $scenario = $this->aScenario();

        self::service(DiscardRandomCardFromHandHandler::class)(new DiscardRandomCardFromHand($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('jane')->getId(), 2));

        self::assertSame(['hyobu'], $this->offered($scenario, 'john'));
        self::assertCount(1, self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getReactionWindows());

        self::service(DiscardCardHandler::class)(new DiscardCard($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('johns_card')->getId()));

        self::assertSame(['hyobu', 'motivator'], $this->offered($scenario, 'john'));
    }

    public function testAPlayerLooksAtTheHandOfTheirOpponent(): void
    {
        $scenario = $this->aScenario();

        self::service(LookAtCardsHandler::class)(new LookAtCards($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('jane')->getId(), HiddenPile::Hand));

        self::assertSame(['warrior', 'yoriki'], $this->offered($scenario, 'john'));
        self::assertSame([], $this->offered($scenario, 'jane'));
    }

    public function testTheHandOfAPlayerRevealed(): void
    {
        $scenario = $this->aScenario();

        self::service(RevealCardsHandler::class)(new RevealCards($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('john')->getId(), HiddenPile::Hand));

        self::assertSame([], $this->offered($scenario, 'john'));
        self::assertSame(['janes_warrior'], $this->offered($scenario, 'jane'));
    }
}
