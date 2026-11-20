<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\PersonalHonor;
use App\Enum\StatusToken;
use App\Event\PersonalHonorChangedEvent;
use App\Event\StatusTokenPlacedEvent;
use App\Message\DishonorCharacter;
use App\Message\HonorCharacter;
use App\Message\PlaceStatusToken;
use App\MessageHandler\DishonorCharacterHandler;
use App\MessageHandler\HonorCharacterHandler;
use App\MessageHandler\PlaceStatusTokenHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The cards reacting to a character honored or dishonored, or receiving a status token.
 */
class PersonalHonorReactionsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['politician', 'artisan', 'omoidasu', 'whisper', 'janes_whisper'];

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

    private function aScenario(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['politician' => '01-savvy-politician'], personalHonor: PersonalHonor::Dishonored)
                ->inPlay(['artisan' => '18-armament-artisan'])
                ->inPlay(['omoidasu' => '27-ardent-omoidasu'])
                ->hand(['whisper' => '40-final-whisper']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['janes_whisper' => '40-final-whisper']))
            ->persist();
    }

    public function testEveryCardReactingToAStatusSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing(
            // and Isawa Tsuke, by the effect of the fire ring (RingEffectReactionsTest)
            ['01-savvy-politician', '18-armament-artisan', '27-ardent-omoidasu', '40-final-whisper', '27-isawa-tsuke'],
            $this->subscribers(PersonalHonorChangedEvent::class),
        );
        self::assertEqualsCanonicalizing(['40-final-whisper'], $this->subscribers(StatusTokenPlacedEvent::class));
    }

    public function testADishonoredCharacterHonoredHasBeenHonored(): void
    {
        $scenario = $this->aScenario();

        self::service(HonorCharacterHandler::class)(new HonorCharacter($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('politician')->getId()));

        self::assertSame(['politician', 'artisan'], $this->offered($scenario, 'john'));
        self::assertSame(['janes_whisper'], $this->offered($scenario, 'jane'));
    }

    public function testACharacterDishonored(): void
    {
        $scenario = $this->aScenario();

        self::service(DishonorCharacterHandler::class)(new DishonorCharacter($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('artisan')->getId()));

        self::assertSame(['omoidasu'], $this->offered($scenario, 'john'));
        self::assertSame(['janes_whisper'], $this->offered($scenario, 'jane'));
    }

    public function testAStatusTokenPlacedIsNotHonoring(): void
    {
        $scenario = $this->aScenario();

        self::service(PlaceStatusTokenHandler::class)(new PlaceStatusToken($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('artisan')->getId(), StatusToken::Honored));

        self::assertSame([], $this->offered($scenario, 'john'));
        self::assertSame(['janes_whisper'], $this->offered($scenario, 'jane'));
    }
}
