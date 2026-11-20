<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\HonorGainedEvent;
use App\Event\HonorLostEvent;
use App\Event\HonorTransferredEvent;
use App\Message\GainHonor;
use App\Message\LoseHonor;
use App\MessageHandler\GainHonorHandler;
use App\MessageHandler\LoseHonorHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\HonorTransferredTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

/**
 * The cards reacting to the honor of a player: gained, lost (to the general token pool) or given by an honor bid.
 */
class HonorReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['chrysanthemum', 'intimidator', 'heroine', 'samurai', 'peacekeeper', 'janes_chrysanthemum'];

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

    private function scenarioAt(string $place, int $johnsBid = 0, int $janesBid = 0): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->honorBid($johnsBid)
                ->inPlay(['intimidator' => '09-heartless-intimidator'])
                ->inPlay(['heroine' => '16-shiotome-heroine'])
                ->inPlay(['samurai' => '34-righteous-samurai'])
                ->inPlay(['peacekeeper' => '36-sinister-peacekeeper'])
                ->hand(['chrysanthemum' => '05-way-of-the-chrysanthemum']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->honorBid($janesBid)
                ->hand(['janes_chrysanthemum' => '05-way-of-the-chrysanthemum']))
            ->persist();
    }

    public function testEveryCardReactingToHonorSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing(['16-shiotome-heroine', '36-sinister-peacekeeper'], $this->subscribers(HonorGainedEvent::class));
        self::assertEqualsCanonicalizing(['09-heartless-intimidator', '34-righteous-samurai'], $this->subscribers(HonorLostEvent::class));
        self::assertEqualsCanonicalizing(['05-way-of-the-chrysanthemum'], $this->subscribers(HonorTransferredEvent::class));
    }

    public function testAnOpponentGainsHonorThroughACardEffect(): void
    {
        $scenario = $this->scenarioAt('conflict_phase_action_window');

        self::service(GainHonorHandler::class)(new GainHonor($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->player('jane')->getId(), 1));

        self::assertSame(['heroine', 'peacekeeper'], $this->offered($scenario, 'john'));
    }

    public function testHonorLost(): void
    {
        $scenario = $this->scenarioAt('conflict_phase_action_window');

        self::service(LoseHonorHandler::class)(new LoseHonor($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->player('jane')->getId(), 1));
        self::service(LoseHonorHandler::class)(new LoseHonor($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->player('john')->getId(), 1));

        self::assertSame(['intimidator', 'samurai'], $this->offered($scenario, 'john'));
    }

    public function testHonorGivenByAnHonorBid(): void
    {
        $scenario = $this->scenarioAt('honor_bid', johnsBid: 1, janesBid: 4);

        self::service(HonorTransferredTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        // jane gives 3 honor to john: neither lost nor gained
        self::assertSame(['chrysanthemum'], $this->offered($scenario, 'john'));
        self::assertSame([], $this->offered($scenario, 'jane'));
    }
}
