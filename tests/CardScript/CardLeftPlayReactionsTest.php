<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\CardLeftPlayEvent;
use App\Message\DiscardCard;
use App\MessageHandler\DiscardCardHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to a character leaving play.
 */
class CardLeftPlayReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['berserker', 'taka', 'janes_berserker'];

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

    private function scenarioAt(string $place, bool $conflict): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['berserker' => '01-vengeful-berserker'])
                ->inPlay(['taka' => '06-yasuki-taka'])
                ->inPlay(['crab' => '01-hida-kisada', 'attachment' => '01-fine-katana']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['janes_berserker' => '01-vengeful-berserker'])
                ->province('01-entrenched-position', as: 'province'));

        return ($conflict ? $builder->conflict('john', 'province', attackers: ['berserker']) : $builder)->persist();
    }

    public function testEveryCardReactingToACharacterLeavingPlaySubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(CardLeftPlayEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(['01-vengeful-berserker', '06-yasuki-taka'], $codes);
    }

    public function testACrabCharacterLeavesPlayDuringAConflict(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', true);

        self::service(DiscardCardHandler::class)(new DiscardCard($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('crab')->getId()));

        self::assertSame(['berserker', 'taka'], $this->offered($scenario, 'john'));
        self::assertSame([], $this->offered($scenario, 'jane'));
    }

    public function testAnAttachmentLeavingPlayIsNotACharacter(): void
    {
        $scenario = $this->scenarioAt('conflict_action_window', true);

        self::service(DiscardCardHandler::class)(new DiscardCard($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('attachment')->getId()));

        self::assertSame([], $this->offered($scenario, 'john'));
    }

    public function testACrabCharacterLeavesPlayOutsideTheConflictPhase(): void
    {
        $scenario = $this->scenarioAt('fate_phase_action_window', false);

        self::service(DiscardCardHandler::class)(new DiscardCard($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('crab')->getId()));

        self::assertSame([], $this->offered($scenario, 'john'));
    }
}
