<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\CharacterMovedToConflictEvent;
use App\Message\MoveToConflict;
use App\MessageHandler\MoveToConflictHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to a character moving to a conflict (CharacterMovedToConflictEvent); Spyglass and Daikyū also react
 * to a character committing to it (declared as an attacker or a defender), Hida Tsuru to a character played into it.
 */
class CharacterMovedToConflictReactionsTest extends AbstractKernelTestCase
{
    private const array CODES = [
        '01-ide-trader', '01-spyglass', '16-outskirts-sentry', '17-hida-tsuru', '30-moto-stables',
        '30-twilight-rider', '42-daikyu',
    ];

    private const array ALIASES = ['trader', 'sentry', 'tsuru', 'stables', 'rider', 'spyglass', 'daikyu', 'janes_trader', 'janes_stables'];

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

    public function testEveryCardReactingToACharacterMovingToAConflictSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(CharacterMovedToConflictEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testACharacterMovesToTheConflict(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['trader' => '01-ide-trader'])
                ->inPlay(['sentry' => '16-outskirts-sentry'])
                ->inPlay(['tsuru' => '17-hida-tsuru'])
                ->inPlay(['rider' => '30-twilight-rider', 'spyglass' => '01-spyglass'])
                ->inPlay(['archer' => '01-togashi-initiate', 'daikyu' => '42-daikyu'])
                ->province('01-entrenched-position', dynasty: '30-moto-stables', dynastyAs: 'stables', dynastyFaceup: true))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['janes_trader' => '01-ide-trader'])
                ->province('01-entrenched-position', dynasty: '30-moto-stables', dynastyAs: 'janes_stables', dynastyFaceup: true)
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['trader', 'sentry', 'archer'], defenders: ['janes_trader'])
            ->persist();

        self::service(MoveToConflictHandler::class)(new MoveToConflict(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('rider')->getId(),
        ));

        // not Hida Tsuru, at home; Daikyū: a military conflict its character participates in
        self::assertSame(['trader', 'sentry', 'stables', 'rider', 'spyglass', 'daikyu'], $this->offered($scenario, 'john'));
        // Ide Trader participates on the other side
        self::assertSame(['janes_trader'], $this->offered($scenario, 'jane'));
    }
}
