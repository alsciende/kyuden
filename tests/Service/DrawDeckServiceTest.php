<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Enum\StackType;
use App\Exception\Rules\MinimumHonorReachedException;
use App\Service\DrawDeckService;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

class DrawDeckServiceTest extends AbstractKernelTestCase
{
    public function testReturnsTheTopCardOfANonEmptyDeckWithoutLosingHonor(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->conflictDeck([
                    'bottom' => '01-banzai',
                    'top' => '01-fine-katana',
                ])
                ->conflictDiscardPile([
                    'discarded' => '01-banzai',
                ]))
            ->withPlayer('jane')
            ->build();
        $john = $scenario->player('john');

        $card = self::service(DrawDeckService::class)->getTopCard($john, StackType::ConflictDrawDeck);

        self::assertSame($scenario->card('top'), $card);
        self::assertSame(10, $this->playerStateManager->getState($john)->getHonor());
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('discarded')->getCurrentPlace());
    }

    public function testAnEmptyConflictDeckCostsFiveHonorAndIsFormedFromTheDiscardPile(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->conflictDiscardPile([
                    'banzai' => '01-banzai',
                    'katana' => '01-fine-katana',
                ]))
            ->withPlayer('jane')
            ->build();
        $john = $scenario->player('john');

        $card = self::service(DrawDeckService::class)->getTopCard($john, StackType::ConflictDrawDeck);

        self::assertSame(5, $this->playerStateManager->getState($john)->getHonor());
        self::assertSame(ConflictCard::STATE_DRAW_DECK, $scenario->card('banzai')->getCurrentPlace());
        self::assertSame(ConflictCard::STATE_DRAW_DECK, $scenario->card('katana')->getCurrentPlace());
        self::assertContains($card, [$scenario->card('banzai'), $scenario->card('katana')]);
        self::assertCount(0, $john->getUniqueStack(StackType::ConflictDiscardPile)->getCards());
    }

    public function testAnEmptyDynastyDeckIsFormedFromTheDynastyDiscardPile(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->dynastyDiscardPile([
                    'toturi' => '01-akodo-toturi',
                ]))
            ->withPlayer('jane')
            ->build();
        $john = $scenario->player('john');

        $card = self::service(DrawDeckService::class)->getTopCard($john, StackType::DynastyDrawDeck);

        self::assertSame($scenario->card('toturi'), $card);
        self::assertSame(DynastyCard::STATE_DRAW_DECK, $card->getCurrentPlace());
        self::assertSame(5, $this->playerStateManager->getState($john)->getHonor());
    }

    public function testAnEmptyDiscardPileLeavesTheDeckEmptyAfterTheHonorLoss(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane')
            ->build();
        $john = $scenario->player('john');

        $card = self::service(DrawDeckService::class)->getTopCard($john, StackType::ConflictDrawDeck);

        self::assertNull($card);
        self::assertSame(5, $this->playerStateManager->getState($john)->getHonor());
    }

    public function testRunningOutOfCardsCanMakeThePlayerReachMinimumHonor(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(5)
                ->conflictDiscardPile(['01-banzai']))
            ->withPlayer('jane')
            ->build();

        $this->expectException(MinimumHonorReachedException::class);

        self::service(DrawDeckService::class)->getTopCard($scenario->player('john'), StackType::ConflictDrawDeck);
    }

    public function testRejectsAStackThatIsNotADrawDeck(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane')
            ->build();

        $this->expectException(\InvalidArgumentException::class);

        self::service(DrawDeckService::class)->getTopCard($scenario->player('john'), StackType::Hand);
    }
}
