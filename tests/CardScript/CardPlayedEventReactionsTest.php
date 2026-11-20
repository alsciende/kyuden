<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Message\PlayActionEvent;
use App\MessageHandler\PlayActionEventHandler;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to an event played ("After you play a Spell card during a conflict", "After an opponent plays a
 * card during a conflict in which this character is participating"), CardPlayedEvent once the event is in the discard
 * pile. The cards of CardPlayedEvent are listed by CardPlayedReactionsTest.
 */
class CardPlayedEventReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = [
        'tetsu', 'heiko', 'shigeru', 'johns_hotaru', 'johns_poet', 'gaze', 'hotaru', 'poet', 'hosts', 'shigeru_at_home',
    ];

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

    public function testAnEventPlayedDuringAConflict(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->honor(10)
                ->inPlay(['tetsu' => '08-shiba-tetsu'])
                ->inPlay(['heiko' => '21-isawa-heiko'])
                ->inPlay(['shigeru' => '08-doji-shigeru'])
                ->inPlay(['johns_hotaru' => '21-doji-hotaru'])
                ->inPlay(['johns_poet' => '19-sententious-poet'])
                ->hand(['waves' => '01-against-the-waves']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(5)
                ->inPlay(['shugenja' => '01-togashi-initiate', 'gaze' => '09-the-mirror-s-gaze'])
                ->inPlay(['hotaru' => '21-doji-hotaru'])
                ->inPlay(['poet' => '19-sententious-poet'])
                ->inPlay(['hosts' => '18-winter-court-hosts'])
                ->inPlay(['shigeru_at_home' => '08-doji-shigeru'])
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['tetsu', 'shigeru', 'johns_hotaru', 'johns_poet'], defenders: ['hotaru', 'poet', 'hosts'])
            ->activePlayer('john')
            ->persist();

        self::service(PlayActionEventHandler::class)(new PlayActionEvent(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card('waves')->getId(),
            1,
        ));

        // a Spell and a Water card played by john; nothing of his reacts to his own card
        self::assertSame(['tetsu', 'heiko'], $this->offered($scenario, 'john'));
        // Winter Court Hosts: jane is less honorable; Doji Shigeru does not participate
        self::assertSame(['gaze', 'hotaru', 'poet'], $this->offered($scenario, 'jane'));
    }
}
