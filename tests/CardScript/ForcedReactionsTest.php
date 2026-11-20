<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Entity\LogicalCard;
use App\Message\TriggerReactionAbility;
use App\MessageHandler\TriggerReactionAbilityHandler;
use App\Repository\LogicalCardRepository;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\PhaseBeganCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

/**
 * The forced reactions ("Forced Reaction:") are offered like the others, marked forced (ReactionOffer::$forced): their
 * windows keep which of their cards are forced, until they are triggered.
 */
class ForcedReactionsTest extends AbstractKernelTestCase
{
    /**
     * The cards with a forced reaction, by triggering condition: the tests of each list them too.
     */
    private const array CODES = [
        // a phase begins
        '01-steadfast-samurai', '13-kuni-laboratory', '24-bayushi-shoju',
        // a conflict won or lost
        '16-unmatched-expertise', '27-hall-of-victories', '27-total-warfare', '32-mercenary-company',
        // a conflict declared
        '28-temple-of-daikoku', '28-temple-of-jikoju', '28-temple-of-osano-wo', '28-temple-of-shinsei-s-wisdom',
        '28-temple-of-the-thunders',
        // a ring claimed, a pass on declaring a conflict
        '16-isawa-ujina', '08-hida-amoro',
        // declared as an attacker or a defender, defenders declared
        '10-marauding-oni', '39-arrogant-kakita',
    ];

    public function testEveryCardWithAForcedReactionSubscribesToItsTriggeringCondition(): void
    {
        $forced = array_map(
            fn (LogicalCard $card): string => $card->getId(),
            array_filter(
                self::service(LogicalCardRepository::class)->findAll(),
                fn (LogicalCard $card): bool => str_contains($card->getText() ?? '', 'Forced Reaction:'),
            ),
        );
        self::assertEqualsCanonicalizing(self::CODES, array_values($forced));

        $registry = self::service(CardScriptRegistry::class);
        foreach (self::CODES as $code) {
            self::assertNotSame([], $registry->find($code)?->getSubscribedEvents() ?? [], $code);
        }
    }

    private function conflictPhaseBegins(): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '13-kuni-laboratory', dynastyAs: 'laboratory', dynastyFaceup: true)
                ->hand(['hostilities' => '06-waning-hostilities']))
            ->withPlayer('jane')
            ->persist();

        self::service(PhaseBeganCompletedListener::class)(new CompletedEvent(
            $scenario->game(),
            new Marking(),
            new Transition('conflict_phase_began', 'conflict_phase_begins', 'conflict_phase_action_window'),
        ));

        return $scenario;
    }

    /**
     * @return list<ReactionWindow>
     */
    private function windows(Scenario $scenario, string $player): array
    {
        return self::service(PlayerStateManager::class)->getState($scenario->player($player))->getReactionWindows();
    }

    public function testAForcedReactionIsOfferedMarkedForced(): void
    {
        $scenario = $this->conflictPhaseBegins();

        $windows = $this->windows($scenario, 'john');
        self::assertCount(1, $windows);
        self::assertEqualsCanonicalizing([$scenario->card('laboratory')->getId(), $scenario->card('hostilities')->getId()], $windows[0]->cardIds);
        // Waning Hostilities is not forced
        self::assertSame([$scenario->card('laboratory')->getId()], $windows[0]->forcedCardIds);
    }

    public function testAForcedReactionTriggeredLeavesItsWindow(): void
    {
        $scenario = $this->conflictPhaseBegins();

        self::service(TriggerReactionAbilityHandler::class)(new TriggerReactionAbility(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('laboratory')->getId(),
        ));

        $windows = $this->windows($scenario, 'john');
        self::assertSame([$scenario->card('hostilities')->getId()], $windows[0]->cardIds);
        self::assertSame([], $windows[0]->forcedCardIds);
    }
}
