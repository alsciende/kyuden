<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Enum\StackType;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInProvinceException;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\MulliganDynastyCards;
use App\MessageHandler\MulliganDynastyCardsHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class MulliganDynastyCardsHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAt(string $place = 'dynasty_mulligan', string $active = 'john'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'first', dynasty: '01-doji-whisperer', dynastyAs: 'whisperer')
                ->province('01-pilgrimage', as: 'second', dynasty: '01-brash-samurai', dynastyAs: 'samurai')
                ->province('01-night-raid', as: 'third', dynasty: '01-kakita-asami', dynastyAs: 'asami', dynastyFaceup: true)
                ->dynastyDeck(['01-doji-challenger']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-peace', dynasty: '01-akodo-toturi', dynastyAs: 'toturi')
                ->dynastyDeck(['01-savvy-politician']))
            ->firstPlayer('john')
            ->activePlayer($active)
            ->withGameState(function (GameState $state): void {
                $state->mulligan = true;
            })
            ->persist();
    }

    /**
     * @param list<string> $cards aliases
     */
    private function mulligan(Scenario $scenario, string $player, array $cards): void
    {
        self::service(MulliganDynastyCardsHandler::class)(new MulliganDynastyCards(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            array_map(fn (string $card): string => $scenario->card($card)->getId(), $cards),
        ));
    }

    /**
     * @return list<string> the codes of the dynasty cards of the province, from the bottom
     */
    private function dynastyCodes(Scenario $scenario, string $province): array
    {
        $cards = array_values(array_filter($scenario->card($province)->getStack()->getOrderedCards(), fn (PhysicalCard $card): bool => $card instanceof DynastyCard));

        return array_map(fn (PhysicalCard $card): string => $card->getLogicalCard()->getId(), $cards);
    }

    public function testTheCardsAreReplacedThenShuffledBackIntoTheDeck(): void
    {
        $scenario = $this->scenarioAt();

        $this->mulligan($scenario, 'john', ['samurai']);

        self::assertSame(['01-doji-whisperer'], $this->dynastyCodes($scenario, 'first'));
        self::assertSame(['01-doji-challenger'], $this->dynastyCodes($scenario, 'second'));
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $scenario->card('second')->getStack()->getTopCards(1)[0]->getCurrentPlace());
        $deck = $scenario->player('john')->getUniqueStack(StackType::DynastyDrawDeck)->getOrderedCards();
        self::assertSame(['01-brash-samurai'], array_map(fn (PhysicalCard $card): string => $card->getLogicalCard()->getId(), $deck));
        // the next player mulligans
        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([$scenario->player('jane')->getId()], $state->activePlayerIds);
        // john learns his new card, jane only that cards were replaced
        self::assertSame([
            "a facedown card is put facedown into john's province",
            "a facedown card is shuffled from john's province into john's dynasty deck",
            "john's dynasty deck is shuffled",
            "john mulligans 1 of their cards into john's dynasty deck",
            "john looks at Doji Whisperer in john's province",
            "john looks at Doji Challenger in john's province",
            "john looks at Kakita Asami in john's province",
        ], $this->logLines($scenario->game(), $scenario->player('john')));
        // jane only reads that cards were replaced
        self::assertSame([
            "a facedown card is put facedown into john's province",
            "a facedown card is shuffled from john's province into john's dynasty deck",
            "john's dynasty deck is shuffled",
            "john mulligans 1 of their cards into john's dynasty deck",
            "john looks at a facedown card in john's province",
            "john looks at a facedown card in john's province",
            "john looks at Kakita Asami in john's province",
        ], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testNoCardKeepsTheCards(): void
    {
        $scenario = $this->scenarioAt();

        $this->mulligan($scenario, 'john', []);

        self::assertSame(['01-doji-whisperer'], $this->dynastyCodes($scenario, 'first'));
        self::assertSame(['01-brash-samurai'], $this->dynastyCodes($scenario, 'second'));
        self::assertSame(['john keeps their cards'], $this->logLines($scenario->game(), $scenario->player('jane')));
        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([$scenario->player('jane')->getId()], $state->activePlayerIds);
    }

    public function testTheLastPlayerEndsTheStep(): void
    {
        $scenario = $this->scenarioAt(active: 'jane');

        $this->mulligan($scenario, 'jane', ['toturi']);

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([], $state->activePlayerIds);
    }

    public function testOnlyTheActivePlayerMulligans(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(NotActivePlayerException::class);
        $this->mulligan($scenario, 'jane', []);
    }

    public function testOnlyDuringTheDynastyMulligan(): void
    {
        $scenario = $this->scenarioAt('conflict_mulligan');

        $this->expectException(WorkflowException::class);
        $this->mulligan($scenario, 'john', []);
    }

    public function testAFaceupCardIsRefused(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(NotInProvinceException::class);
        $this->mulligan($scenario, 'john', ['asami']);
    }

    public function testACardOfTheOpponentIsRefused(): void
    {
        $scenario = $this->scenarioAt();

        $this->expectException(NotOwnedCardException::class);
        $this->mulligan($scenario, 'john', ['toturi']);
    }
}
