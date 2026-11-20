<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\DuelEndedEvent;
use App\Event\DuelWonEvent;
use App\Message\DeclareDuelStatistic;
use App\Message\EndDuel;
use App\MessageHandler\DeclareDuelStatisticHandler;
use App\MessageHandler\EndDuelHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to a duel won (DuelWonEvent, once both statistics are declared) and to a duel resolved
 * (DuelEndedEvent: Kyūden Kakita). Those reacting to the honor dials of a duel are in HonorDialsRevealedReactionsTest.
 */
class DuelReactionsTest extends AbstractKernelTestCase
{
    private const array WON = ['03-kakita-blade', '11-insult-to-injury', '16-proving-ground', '18-distinguished-dojo'];

    private const array ALIASES = ['blade', 'insult', 'ground', 'dojo', 'janes_ground', 'kakita', 'janes_kakita'];

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

    private function scenarioInDuel(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->honorBid(1)
                ->stronghold('18-kyuden-kakita', as: 'kakita')
                ->inPlay(['challenger' => '01-doji-challenger', 'blade' => '03-kakita-blade'])
                ->province('01-entrenched-position', dynasty: '16-proving-ground', dynastyAs: 'ground', dynastyFaceup: true)
                ->province('01-entrenched-position', dynasty: '18-distinguished-dojo', dynastyAs: 'dojo', dynastyFaceup: true)
                ->hand(['insult' => '11-insult-to-injury']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->honorBid(1)
                ->stronghold('18-kyuden-kakita', as: 'janes_kakita')
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', dynasty: '16-proving-ground', dynastyAs: 'janes_ground', dynastyFaceup: true))
            ->duel('john', ['challenger'], ['spear'], revealed: true)
            ->persist();
    }

    public function testEveryCardReactingToADuelSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing(self::WON, $this->subscribers(DuelWonEvent::class));
        self::assertEqualsCanonicalizing(['18-kyuden-kakita'], $this->subscribers(DuelEndedEvent::class));
    }

    public function testTheDuelWon(): void
    {
        $scenario = $this->scenarioInDuel();

        foreach (['john' => 5, 'jane' => 1] as $player => $statistic) {
            self::service(DeclareDuelStatisticHandler::class)(new DeclareDuelStatistic($scenario->game()->getId(), $scenario->player($player)->getId(), $statistic));
        }

        // Doji Challenger is a Duelist
        self::assertSame(['blade', 'insult', 'ground', 'dojo'], $this->offered($scenario, 'john'));
        self::assertSame([], $this->offered($scenario, 'jane'));
    }

    public function testTheDuelResolved(): void
    {
        $scenario = $this->scenarioInDuel();
        self::service(DeclareDuelStatisticHandler::class)(new DeclareDuelStatistic($scenario->game()->getId(), $scenario->player('john')->getId(), 5));
        self::service(DeclareDuelStatisticHandler::class)(new DeclareDuelStatistic($scenario->game()->getId(), $scenario->player('jane')->getId(), 1));

        self::service(EndDuelHandler::class)(new EndDuel($scenario->game()->getId(), $scenario->player('jane')->getId()));

        self::assertContains('kakita', $this->offered($scenario, 'john'));
        self::assertContains('janes_kakita', $this->offered($scenario, 'jane'));
    }
}
