<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Service\CardScript\CardScriptRegistry;
use App\Service\Interrupt\InterruptOfferer;
use App\State\Manager\GameStateManager;
use App\Tests\Builder\Scenario;
use App\Trigger\ImminentTriggerInterface;

/**
 * The cards an interrupt window offers for imminent conditions, as aliases, for the tests of the scripts.
 */
trait InterruptOffers
{
    /**
     * The window opened for the conditions: the aliases of the cards offered, by `player:stage` (the players named
     * `john` and `jane`), and the players asked by stage without a card offered (an event in the deck) as
     * `player:stage` => [].
     *
     * @param list<ImminentTriggerInterface> $triggers
     * @param list<string>                   $aliases  the cards that may be offered
     *
     * @return array<string, list<string>>
     */
    private function offers(Scenario $scenario, array $triggers, array $aliases): array
    {
        if (! self::service(InterruptOfferer::class)->open($scenario->game(), $triggers)) {
            return [];
        }

        $window = self::service(GameStateManager::class)->getState($scenario->game())->currentInterruptWindow();
        $offers = [];
        foreach ($window->stages ?? [] as $stage) {
            foreach ($stage->askedPlayerIds as $playerId) {
                $player = $playerId === $scenario->player('john')->getId() ? 'john' : 'jane';
                $cardIds = $stage->cardIds[$playerId] ?? [];
                $offers["{$player}:{$stage->stage->value}"] = array_values(array_filter(
                    $aliases,
                    fn (string $candidate): bool => in_array($scenario->card($candidate)->getId(), $cardIds, true),
                ));
            }
        }

        ksort($offers);

        return $offers;
    }

    /**
     * The codes of the scripts subscribing to the condition.
     *
     * @param class-string<ImminentTriggerInterface> $trigger
     *
     * @return list<string>
     */
    private function subscribers(string $trigger): array
    {
        $registry = self::service(CardScriptRegistry::class);

        return array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists($trigger, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));
    }
}
