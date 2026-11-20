<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\HonorBidPlacedEvent;
use App\Event\HonorDialsRevealedEvent;
use App\EventListener\DuelHonorBidPlacedListener;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\HonorBidsConfirmedTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

/**
 * The cards reacting to the honor dials revealed, of the draw phase or of a duel: the conditions of their scripts.
 */
class HonorDialsRevealedReactionsTest extends AbstractKernelTestCase
{
    private const array CODES = [
        '01-bayushi-manipulator', '01-contingency-plan', '13-tactician-s-apprentice', '15-akodo-kage',
        '16-ide-negotiator', '18-kitsuki-jusai', '28-privileged-position', '16-iaijutsu-master', '20-niten-pupil',
    ];

    /**
     * @param list<string> $aliases
     *
     * @return list<string> the cards offered to the player, by alias, among those given
     */
    private function offered(Scenario $scenario, string $player, array $aliases): array
    {
        $cardIds = array_merge(...array_map(
            fn (ReactionWindow $window): array => $window->cardIds,
            self::service(PlayerStateManager::class)->getState($scenario->player($player))->getReactionWindows(),
        ));

        return array_values(array_filter($aliases, fn (string $alias): bool => in_array($scenario->card($alias)->getId(), $cardIds, true)));
    }

    public function testEveryCardReactingToTheHonorDialsRevealedSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(HonorDialsRevealedEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testTheBidsOfTheDrawPhase(): void
    {
        $scenario = $this->scenario()
            ->atPlace('honor_bid')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['apprentice' => '13-tactician-s-apprentice'])
                ->inPlay(['manipulator' => '01-bayushi-manipulator'])
                ->inPlay(['kage' => '15-akodo-kage'])
                ->inPlay(['pupil' => '20-niten-pupil'])
                ->pendingHonorBid(2)
                ->honor(12))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['jusai' => '18-kitsuki-jusai'])
                ->inPlay(['negotiator' => '16-ide-negotiator'])
                ->pendingHonorBid(4)
                ->honor(8))
            ->persist();
        $aliases = ['apprentice', 'manipulator', 'kage', 'pupil', 'jusai', 'negotiator'];

        self::service(HonorBidsConfirmedTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        // a lower bid, a lower bid and more honor; not Niten Pupil, outside of a duel
        self::assertSame(['apprentice', 'manipulator', 'kage'], $this->offered($scenario, 'john', $aliases));
        // the bids differ
        self::assertSame(['negotiator'], $this->offered($scenario, 'jane', $aliases));
    }

    public function testTheBidsOfADuel(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['pupil' => '20-niten-pupil'])
                ->inPlay(['other_pupil' => '20-niten-pupil'])
                ->pendingHonorBid(3))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['duelist' => '01-togashi-yokuni', 'master' => '16-iaijutsu-master'])
                ->pendingHonorBid(3))
            ->duel('john', ['pupil'], ['duelist'])
            ->persist();

        self::service(DuelHonorBidPlacedListener::class)(new HonorBidPlacedEvent($scenario->game(), $scenario->player('jane'), 3));

        self::assertSame(['pupil'], $this->offered($scenario, 'john', ['pupil', 'other_pupil']));
        self::assertSame(['master'], $this->offered($scenario, 'jane', ['master']));
    }
}
