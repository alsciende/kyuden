<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\ConflictPassedEvent;
use App\Message\PassConflict;
use App\MessageHandler\PassConflictHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to a pass on declaring a conflict: the conditions of their scripts.
 */
class ConflictPassedReactionsTest extends AbstractKernelTestCase
{
    /**
     * The cards of the Stronghold format, Hida Amoro (a forced reaction) included.
     */
    private const array CODES = [
        // the forced reactions (ForcedReactionsTest)
        '08-hida-amoro',
        '01-intimidating-hida', '08-pacifist-philosopher', '26-fruitful-respite', '28-mediator-of-hostilities',
        '28-one-of-the-forgotten', '29-our-foe-does-not-wait', '32-kuni-purifier', '33-asako-lawmaster',
        '33-endless-archives',
    ];

    private const array ALIASES = ['lawmaster', 'johns_philosopher', 'hida', 'forgotten', 'respite'];

    private function pass(Scenario $scenario): void
    {
        self::service(PassConflictHandler::class)(new PassConflict($scenario->game()->getId(), $scenario->player('john')->getId()));
    }

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

    private function scenarioWith(bool $bowed): Scenario
    {
        return $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['lawmaster' => '33-asako-lawmaster'], bowed: $bowed)
                ->inPlay(['johns_philosopher' => '08-pacifist-philosopher'], bowed: $bowed))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['hida' => '01-intimidating-hida'])
                ->inPlay(['forgotten' => '28-one-of-the-forgotten'])
                ->hand(['respite' => '26-fruitful-respite']))
            ->activePlayer('john')
            ->persist();
    }

    public function testEveryCardReactingToAPassOnDeclaringAConflictSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(ConflictPassedEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(self::CODES, $codes);
    }

    public function testThePlayerWhoPassesAndTheirOpponent(): void
    {
        $scenario = $this->scenarioWith(bowed: false);

        $this->pass($scenario);

        self::assertSame(['lawmaster', 'johns_philosopher'], $this->offered($scenario, 'john'));
        self::assertSame(['hida', 'forgotten', 'respite'], $this->offered($scenario, 'jane'));
    }

    public function testTheCardsAskingForAReadyCharacterOfThePlayerWhoPasses(): void
    {
        $scenario = $this->scenarioWith(bowed: true);

        $this->pass($scenario);

        // the bowed characters of John trigger their own reactions all the same
        self::assertSame(['lawmaster', 'johns_philosopher'], $this->offered($scenario, 'john'));
        self::assertSame(['hida'], $this->offered($scenario, 'jane'));
    }
}
