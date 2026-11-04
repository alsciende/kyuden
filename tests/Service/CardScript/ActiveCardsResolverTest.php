<?php

declare(strict_types=1);

namespace App\Tests\Service\CardScript;

use App\Model\ActiveCard;
use App\Service\CardScript\ActiveCardsResolver;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class ActiveCardsResolverTest extends AbstractKernelTestCase
{
    /**
     * @param list<string> $aliases
     *
     * @return list<string> the active cards of the player, by alias, among those given
     */
    private function activeCards(Scenario $scenario, string $player, array $aliases): array
    {
        $ids = array_map(
            fn (ActiveCard $activeCard): string => $activeCard->card->getId(),
            self::service(ActiveCardsResolver::class)->resolve($scenario->game(), $scenario->player($player)),
        );

        return array_values(array_filter($aliases, fn (string $alias): bool => in_array($scenario->card($alias)->getId(), $ids, true)));
    }

    public function testThePlayerKnowsTheCardsTheyOwnOutOfPlayWhoseFaceTheySee(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['levy' => '01-levy', 'katana' => '01-fine-katana'])
                ->conflictDiscardPile(['tetsubo' => '01-jade-tetsubo'])
                ->conflictDeck(['kazue' => '01-togashi-kazue'])
                ->province('01-the-art-of-peace', dynasty: '01-doji-gift-giver', dynastyAs: 'gift_giver', dynastyFaceup: true)
                ->province('01-entrenched-position', dynasty: '01-meddling-mediator', dynastyAs: 'mediator'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['know_the_world' => '01-know-the-world']))
            ->build();
        $aliases = ['levy', 'katana', 'tetsubo', 'kazue', 'gift_giver', 'mediator', 'know_the_world'];

        // not a card without a script, nor the cards of their deck, nor their facedown dynasty cards, nor the cards of
        // the opponent
        self::assertSame(['levy', 'tetsubo', 'gift_giver'], $this->activeCards($scenario, 'john', $aliases));
        self::assertSame(['know_the_world'], $this->activeCards($scenario, 'jane', $aliases));
    }

    public function testThePlayerKnowsTheCardsTheyControlInPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['visionary' => '01-ascetic-visionary', 'daisho' => '01-ancestral-daisho']))
            ->withPlayer('jane')
            ->build();
        self::service(CardStateManager::class)->withState(
            $scenario->card('daisho'),
            fn (CardState $state): CardState => $state->setControllerId($scenario->player('jane')->getId()),
        );

        self::assertSame(['visionary'], $this->activeCards($scenario, 'john', ['visionary', 'daisho']));
        self::assertSame(['daisho'], $this->activeCards($scenario, 'jane', ['visionary', 'daisho']));
    }
}
