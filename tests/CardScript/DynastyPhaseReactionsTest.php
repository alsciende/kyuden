<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\DynastyPhasePassedEvent;
use App\Event\FateCollectedEvent;
use App\Event\OpportunityPassedEvent;
use App\EventListener\PlayCardsFromProvincesOpportunityPassedListener;
use App\Message\CollectFate;
use App\MessageHandler\CollectFateHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to the dynasty phase: a player passing (Sneaky Shinjo and Ride at Dawn from their provinces),
 * gaining fate for passing first, collecting fate.
 */
class DynastyPhaseReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['scout', 'shiro', 'sneaky', 'ride'];

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

    private function scenarioAt(string $place): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('17-shiro-shinjo', as: 'shiro')
                ->inPlay(['scout' => '07-shinjo-scout'])
                ->province('01-entrenched-position', dynasty: '10-sneaky-shinjo', dynastyAs: 'sneaky', dynastyFaceup: true)
                ->province('01-entrenched-position', dynasty: '32-ride-at-dawn', dynastyAs: 'ride', dynastyFaceup: true))
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();
    }

    public function testEveryCardReactingToTheDynastyPhaseSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing(['07-shinjo-scout', '10-sneaky-shinjo', '32-ride-at-dawn'], $this->subscribers(DynastyPhasePassedEvent::class));
        self::assertEqualsCanonicalizing(['17-shiro-shinjo'], $this->subscribers(FateCollectedEvent::class));
    }

    public function testThePlayerPassingFirst(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        self::service(PlayCardsFromProvincesOpportunityPassedListener::class)(new OpportunityPassedEvent($scenario->game(), $scenario->player('john'), true));

        self::assertSame(['scout', 'sneaky', 'ride'], $this->offered($scenario, 'john'));
    }

    public function testThePlayerPassingSecondGainsNoFate(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        self::service(PlayCardsFromProvincesOpportunityPassedListener::class)(new OpportunityPassedEvent($scenario->game(), $scenario->player('john'), false));

        // Sneaky Shinjo and Ride at Dawn react to the pass, whatever the fate gained
        self::assertSame(['sneaky', 'ride'], $this->offered($scenario, 'john'));
    }

    public function testThePlayerCollectingFate(): void
    {
        $scenario = $this->scenarioAt('collect_fate');

        self::service(CollectFateHandler::class)(new CollectFate($scenario->game()->getId(), $scenario->player('john')->getId(), 7));

        self::assertSame(['shiro'], $this->offered($scenario, 'john'));
    }
}
