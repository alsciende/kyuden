<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\Orientation;
use App\Event\AbilityTriggeredEvent;
use App\Message\TriggerActionAbility;
use App\MessageHandler\TriggerActionAbilityHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to a character triggering an ability.
 */
class AbilityTriggeredReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['kamayari', 'kireiko', 'johns_kireiko'];

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

    private function aScenario(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['initiate' => '01-togashi-initiate'])
                ->province('01-entrenched-position', dynasty: '01-favorable-ground', dynastyAs: 'ground', dynastyFaceup: true)
                ->hand(['johns_kireiko' => '16-kirei-ko']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['bushi' => '01-hida-kisada', 'kamayari' => '03-kamayari'])
                ->hand(['kireiko' => '16-kirei-ko'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['initiate'], defenders: ['bushi'])
            ->activePlayer('john')
            ->persist();
    }

    public function testEveryCardReactingToAnAbilityTriggeredSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(AbilityTriggeredEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(['03-kamayari', '16-kirei-ko'], $codes);
    }

    public function testACharacterTriggersAnAbility(): void
    {
        $scenario = $this->aScenario();

        self::service(TriggerActionAbilityHandler::class)(new TriggerActionAbility($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('initiate')->getId()));

        self::assertSame([], $this->offered($scenario, 'john'));
        self::assertSame(['kamayari', 'kireiko'], $this->offered($scenario, 'jane'));
    }

    public function testAHoldingIsNotACharacter(): void
    {
        $scenario = $this->aScenario();

        self::service(TriggerActionAbilityHandler::class)(new TriggerActionAbility($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('ground')->getId()));

        self::assertSame([], $this->offered($scenario, 'jane'));
    }
}
