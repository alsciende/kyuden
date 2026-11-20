<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\Orientation;
use App\Event\DefendersDeclaredEvent;
use App\Message\DeclareDefenders;
use App\MessageHandler\DeclareDefendersHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\AttackedProvinceRevealedCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;

/**
 * The cards reacting to a character declared as an attacker or a defender (Ancient Master, Ki Alignment): the attackers
 * once the declaration is complete (ConflictDeclarationCompletedEvent), the defenders once declared
 * (DefendersDeclaredEvent).
 */
class DeclaredAsAttackerOrDefenderReactionsTest extends AbstractKernelTestCase
{
    /**
     * The cards of the Stronghold format reacting to the defenders declared, but the forced reactions (Marauding Oni,
     * Arrogant Kakita); Spyglass and Daikyū react to a character committing to a conflict
     * (CharacterMovedToConflictReactionsTest).
     */
    private const array CODES = ['09-ancient-master', '38-ki-alignment', '01-spyglass', '42-daikyu'];

    private const array ALIASES = ['master', 'johns_alignment', 'janes_master', 'janes_alignment'];

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

    private function scenarioAt(string $place): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['monk' => '01-togashi-initiate', 'master' => '09-ancient-master'])
                ->hand(['johns_alignment' => '38-ki-alignment']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['janes_monk' => '01-togashi-initiate', 'janes_master' => '09-ancient-master'])
                ->hand(['janes_alignment' => '38-ki-alignment'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['monk'])
            ->activePlayer('jane')
            ->persist();
    }

    public function testEveryCardReactingToTheDefendersDeclaredSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(DefendersDeclaredEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testTheAttackers(): void
    {
        $scenario = $this->scenarioAt('react_to_conflict_declaration');

        self::service(AttackedProvinceRevealedCompletedListener::class)(new CompletedEvent($scenario->game(), new Marking()));

        self::assertSame(['master', 'johns_alignment'], $this->offered($scenario, 'john'));
        self::assertSame([], $this->offered($scenario, 'jane'));
    }

    public function testTheDefenders(): void
    {
        $scenario = $this->scenarioAt('declare_defenders');

        self::service(DeclareDefendersHandler::class)(new DeclareDefenders(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            [$scenario->card('janes_monk')->getId()],
        ));

        self::assertSame([], $this->offered($scenario, 'john'));
        self::assertSame(['janes_master', 'janes_alignment'], $this->offered($scenario, 'jane'));
    }
}
