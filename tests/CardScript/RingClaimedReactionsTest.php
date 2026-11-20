<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Event\RingClaimedEvent;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The cards reacting to a ring claimed ("After you claim a ring during a conflict…", "After the [element-water] ring
 * is claimed"): the conditions of their scripts.
 */
class RingClaimedReactionsTest extends AbstractKernelTestCase
{
    /**
     * The cards of the Stronghold format, but Isawa Ujina (a forced reaction).
     */
    private const array CODES = [
        '01-akodo-toturi', '01-doji-hotaru', '01-keeper-initiate', '01-seeker-initiate', '07-asako-tsuki', '09-kudaka',
        '26-kansen-haunt',
    ];

    private function claim(Scenario $scenario, string $player, Element $ring): void
    {
        self::service(EventDispatcherInterface::class)->dispatch(new RingClaimedEvent($scenario->game(), $scenario->player($player), $ring));
    }

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

    public function testEveryCardReactingToARingClaimedSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(RingClaimedEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testTheCardsReactingToTheRingTheirPlayerClaimsAfterAConflict(): void
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_ring_claim')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['hotaru' => '01-doji-hotaru'])
                ->inPlay(['at_home' => '01-doji-hotaru'])
                ->inPlay(['toturi' => '01-akodo-toturi'])
                ->inPlay(['kudaka' => '09-kudaka']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['tsuki' => '07-asako-tsuki'])
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['hotaru', 'toturi'], ring: Element::Water, type: ConflictType::Political)
            ->persist();
        $aliases = ['hotaru', 'at_home', 'toturi', 'kudaka', 'tsuki'];

        $this->claim($scenario, 'john', Element::Water);

        // a political conflict, Doji Hotaru participating; not the air ring of Kudaka
        self::assertSame(['hotaru'], $this->offered($scenario, 'john', $aliases));
        // whoever claims the water ring
        self::assertSame(['tsuki'], $this->offered($scenario, 'jane', $aliases));
    }

    public function testTheCardsReadingTheRoleAndTheHonorOfTheirPlayer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_ring_claim')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['attacker' => '01-togashi-yokuni'])
                ->honor(12))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['initiate' => '01-seeker-initiate'])
                ->inPlay(['keeper_initiate' => '01-keeper-initiate'])
                ->role('01-seeker-of-fire')
                ->honor(8)
                ->province('01-entrenched-position', as: 'province', dynasty: '26-kansen-haunt', dynastyAs: 'haunt', dynastyFaceup: true))
            ->conflict('john', 'province', attackers: ['attacker'], ring: Element::Fire)
            ->persist();
        $aliases = ['initiate', 'keeper_initiate', 'haunt'];

        $this->claim($scenario, 'jane', Element::Fire);

        // both initiates match the element of the role (their role restriction is the deck's business)
        self::assertSame(['initiate', 'keeper_initiate', 'haunt'], $this->offered($scenario, 'jane', $aliases));
    }
}
