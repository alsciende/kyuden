<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\ConflictType;
use App\Enum\Orientation;
use App\Event\CardBowedEvent;
use App\Event\CardReadiedEvent;
use App\Message\BowCard;
use App\Message\ReadyCard;
use App\MessageHandler\BowCardHandler;
use App\MessageHandler\ReadyCardHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\ReturnedHomeTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

/**
 * The cards reacting to a character bowed (by a card effect, or as its conflict is resolved) or readied.
 */
class BowedOrReadiedReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['battle', 'favor', 'sky', 'janes_sky'];

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

    private function scenarioAt(string $place, bool $bowed = false): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->declaredConflicts(ConflictType::Political, ConflictType::Military)
                ->inPlay(['initiate' => '01-togashi-initiate'], bowed: $bowed)
                ->hand(['battle' => '01-ready-for-battle', 'favor' => '05-curry-favor', 'sky' => '20-earth-becomes-sky']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['janes_sky' => '20-earth-becomes-sky'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['initiate'])
            ->persist();
    }

    public function testEveryCardReactingToACharacterBowedOrReadiedSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing(['01-ready-for-battle', '05-curry-favor'], $this->subscribers(CardBowedEvent::class));
        self::assertEqualsCanonicalizing(['20-earth-becomes-sky'], $this->subscribers(CardReadiedEvent::class));
    }

    public function testACharacterBowedByACardEffect(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window');

        self::service(BowCardHandler::class)(new BowCard($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('initiate')->getId()));

        self::assertSame(['battle'], $this->offered($scenario, 'john'));
    }

    public function testACharacterBowedAsTheSecondConflictOfItsPlayerIsResolved(): void
    {
        $scenario = $this->scenarioAt('return_home');

        self::service(ReturnedHomeTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        self::assertSame(['favor'], $this->offered($scenario, 'john'));
    }

    public function testACharacterReadied(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', bowed: true);

        self::service(ReadyCardHandler::class)(new ReadyCard($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('initiate')->getId()));

        self::assertSame([], $this->offered($scenario, 'john'));
        self::assertSame(['janes_sky'], $this->offered($scenario, 'jane'));
    }
}
