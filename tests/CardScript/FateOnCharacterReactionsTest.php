<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\FateHolder;
use App\Event\FateMovedEvent;
use App\Event\FatePhaseFateRemovedEvent;
use App\Event\FatePlacedOnCardEvent;
use App\Event\FateRemovedFromCardEvent;
use App\Message\MoveFate;
use App\Message\PlaceFate;
use App\Message\PlayCharacter;
use App\Message\RemoveFate;
use App\MessageHandler\MoveFateHandler;
use App\MessageHandler\PlaceFateHandler;
use App\MessageHandler\PlayCharacterHandler;
use App\MessageHandler\RemoveFateHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\FateRemovedTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

/**
 * The cards reacting to fate placed on or removed from a character, moved to or from it, and removed in the fate phase.
 */
class FateOnCharacterReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['prodigy', 'mai', 'village', 'kami', 'temple', 'sanctuary', 'janes_village'];

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
                ->fate(10)
                ->inPlay(['mai' => '38-kaito-mai'], fate: 2)
                ->inPlay(['kami' => '40-kami-of-ancient-wisdom'])
                ->province('01-entrenched-position', dynasty: '38-shadowed-village', dynastyAs: 'village', dynastyFaceup: true)
                ->province('01-entrenched-position', dynasty: '41-adorned-temple', dynastyAs: 'temple', dynastyFaceup: true)
                ->province('01-entrenched-position', dynasty: '22-starry-heaven-sanctuary', dynastyAs: 'sanctuary', dynastyFaceup: true)
                ->dynastyDiscardPile(['prodigy' => '01-ikoma-prodigy']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '38-shadowed-village', dynastyAs: 'janes_village', dynastyFaceup: true))
            ->activePlayer('john')
            ->persist();
    }

    public function testEveryCardReactingToFateOnACharacterSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing(['01-ikoma-prodigy', '41-adorned-temple'], $this->subscribers(FatePlacedOnCardEvent::class));
        self::assertEqualsCanonicalizing(['38-kaito-mai', '38-shadowed-village', '40-kami-of-ancient-wisdom'], $this->subscribers(FateRemovedFromCardEvent::class));
        self::assertEqualsCanonicalizing(
            ['01-ikoma-prodigy', '38-kaito-mai', '38-shadowed-village', '40-kami-of-ancient-wisdom', '41-adorned-temple'],
            $this->subscribers(FateMovedEvent::class),
        );
        self::assertEqualsCanonicalizing(['22-starry-heaven-sanctuary'], $this->subscribers(FatePhaseFateRemovedEvent::class));
    }

    public function testFatePlacedAsTheCharacterIsPlayedIsNotByACardEffect(): void
    {
        $scenario = $this->scenarioAt('conflict_phase_action_window');

        self::service(PlayCharacterHandler::class)(new PlayCharacter(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('prodigy')->getId(),
            2,
            1,
            1,
            false,
        ));

        self::assertSame(['prodigy'], $this->offered($scenario, 'john'));
    }

    public function testFatePlacedByACardEffect(): void
    {
        $scenario = $this->scenarioAt('conflict_phase_action_window');

        self::service(PlaceFateHandler::class)(new PlaceFate(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card('kami')->getId(),
            1,
        ));

        self::assertSame(['temple'], $this->offered($scenario, 'john'));
    }

    public function testFateRemovedOutsideTheFatePhase(): void
    {
        $scenario = $this->scenarioAt('conflict_phase_action_window');

        self::service(RemoveFateHandler::class)(new RemoveFate(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card('mai')->getId(),
            1,
        ));

        self::assertSame(['mai', 'village', 'kami'], $this->offered($scenario, 'john'));
        // Shadowed Village: a character jane controls
        self::assertSame([], $this->offered($scenario, 'jane'));
    }

    public function testFateMovedFromACharacter(): void
    {
        $scenario = $this->scenarioAt('conflict_phase_action_window');

        self::service(MoveFateHandler::class)(new MoveFate(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            FateHolder::Card,
            $scenario->card('mai')->getId(),
            FateHolder::Card,
            $scenario->card('kami')->getId(),
            1,
        ));

        self::assertSame(['mai', 'village', 'kami', 'temple'], $this->offered($scenario, 'john'));
    }

    public function testFateRemovedInTheFatePhase(): void
    {
        $scenario = $this->scenarioAt('remove_fate_from_characters');

        self::service(FateRemovedTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        // only Starry Heaven Sanctuary, once
        self::assertSame(['sanctuary'], $this->offered($scenario, 'john'));
        self::assertCount(1, self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getReactionWindows());
    }
}
