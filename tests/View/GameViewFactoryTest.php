<?php

declare(strict_types=1);

namespace App\Tests\View;

use App\Entity\PhysicalCard;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\View\GameViewFactory;
use App\View\Model\CardView;
use App\View\Model\GameView;
use App\View\Model\PlayerView;
use App\View\Model\StackView;

class GameViewFactoryTest extends AbstractKernelTestCase
{
    private function view(Scenario $scenario, string $viewer): GameView
    {
        return self::service(GameViewFactory::class)->create($scenario->game(), $scenario->player($viewer));
    }

    private function playerView(GameView $view, Scenario $scenario, string $alias): PlayerView
    {
        foreach ($view->players as $player) {
            if ($player->id === $scenario->player($alias)->getId()) {
                return $player;
            }
        }

        self::fail("No view of player '{$alias}'.");
    }

    private function stackView(GameView $view, Scenario $scenario, string $alias, string $type): StackView
    {
        foreach ($this->playerView($view, $scenario, $alias)->stacks as $stack) {
            if ($stack->type === $type) {
                return $stack;
            }
        }

        self::fail("No {$type} stack for player '{$alias}'.");
    }

    private function cardView(GameView $view, PhysicalCard $card): CardView
    {
        foreach ($view->players as $player) {
            foreach ($player->stacks as $stack) {
                foreach ($stack->cards ?? [] as $cardView) {
                    if ($cardView->id === $card->getId()) {
                        return $cardView;
                    }
                }
            }
        }

        self::fail("No view of card {$card->getId()}.");
    }

    public function testTheHandIsHiddenFromTheOpponents(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->hand(['katana' => '01-fine-katana', 'banzai' => '01-banzai']))
            ->withPlayer('jane')
            ->build();

        $johnsHand = $this->stackView($this->view($scenario, 'john'), $scenario, 'john', 'hand');
        $janesViewOfJohnsHand = $this->stackView($this->view($scenario, 'jane'), $scenario, 'john', 'hand');

        self::assertSame(['01-fine-katana', '01-banzai'], array_map(fn (CardView $card): ?string => $card->code, $johnsHand->cards ?? []));
        self::assertSame(2, $janesViewOfJohnsHand->count);
        self::assertNull($janesViewOfJohnsHand->cards);
    }

    public function testTheDecksAreHiddenFromEveryPlayer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->conflictDeck(['01-fine-katana', '01-banzai'])
                ->dynastyDeck(['01-akodo-toturi']))
            ->withPlayer('jane')
            ->build();

        $view = $this->view($scenario, 'john');

        $conflictDeck = $this->stackView($view, $scenario, 'john', 'conflict_draw_deck');
        $dynastyDeck = $this->stackView($view, $scenario, 'john', 'dynasty_draw_deck');
        self::assertSame([2, null], [$conflictDeck->count, $conflictDeck->cards]);
        self::assertSame([1, null], [$dynastyDeck->count, $dynastyDeck->cards]);
    }

    public function testAFacedownProvinceIsVisibleToItsOwnerOnly(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'facedown')
                ->province('01-ancestral-lands', as: 'revealed', orientation: Orientation::FaceUp))
            ->withPlayer('jane')
            ->build();

        $johnsView = $this->view($scenario, 'john');
        $janesView = $this->view($scenario, 'jane');

        self::assertSame('01-entrenched-position', $this->cardView($johnsView, $scenario->card('facedown'))->code);
        self::assertNull($this->cardView($janesView, $scenario->card('facedown'))->code);
        self::assertSame(Orientation::FaceDown, $this->cardView($janesView, $scenario->card('facedown'))->orientation);
        self::assertSame('01-ancestral-lands', $this->cardView($janesView, $scenario->card('revealed'))->code);
    }

    public function testAFacedownDynastyCardIsHiddenFromEveryPlayer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '01-akodo-toturi', dynastyAs: 'facedown')
                ->province('01-entrenched-position', dynasty: '01-akodo-toturi', dynastyAs: 'faceup', dynastyFaceup: true))
            ->withPlayer('jane')
            ->build();

        $johnsView = $this->view($scenario, 'john');
        $janesView = $this->view($scenario, 'jane');

        self::assertNull($this->cardView($johnsView, $scenario->card('facedown'))->code);
        self::assertNull($this->cardView($janesView, $scenario->card('facedown'))->code);
        self::assertSame('01-akodo-toturi', $this->cardView($janesView, $scenario->card('faceup'))->code);
    }

    public function testThePendingHonorBidIsVisibleToItsOwnerOnly(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_honor_dials')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->pendingHonorBid(3))
            ->withPlayer('jane')
            ->build();

        $johnsView = $this->playerView($this->view($scenario, 'john'), $scenario, 'john');
        $janesViewOfJohn = $this->playerView($this->view($scenario, 'jane'), $scenario, 'john');
        $janesViewOfJane = $this->playerView($this->view($scenario, 'jane'), $scenario, 'jane');

        self::assertSame([3, true], [$johnsView->pendingHonorBid, $johnsView->honorBidSelected]);
        self::assertSame([null, true], [$janesViewOfJohn->pendingHonorBid, $janesViewOfJohn->honorBidSelected]);
        self::assertSame([null, false], [$janesViewOfJane->pendingHonorBid, $janesViewOfJane->honorBidSelected]);
    }

    public function testTheChosenStrongholdProvinceIsVisibleToItsOwnerOnly(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold_province')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->strongholdProvince('01-entrenched-position', as: 'chosen'))
            ->withPlayer('jane')
            ->build();

        self::assertSame($scenario->card('chosen')->getId(), $this->playerView($this->view($scenario, 'john'), $scenario, 'john')->strongholdProvinceId);
        self::assertNull($this->playerView($this->view($scenario, 'jane'), $scenario, 'john')->strongholdProvinceId);
    }

    public function testTheBoardIsPublic(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(11)
                ->honorBid(2)
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'], bowed: true, fate: 2, personalHonor: PersonalHonor::Honored)
                ->conflictDiscardPile(['bottom' => '01-banzai', 'top' => '01-fine-katana']))
            ->withPlayer('jane')
            ->ring(Element::Fire, fate: 1, claimedBy: 'john')
            ->build();

        $view = $this->view($scenario, 'jane');

        $john = $this->playerView($view, $scenario, 'john');
        self::assertSame([11, 2], [$john->honor, $john->honorBid]);
        $toturi = $this->cardView($view, $scenario->card('toturi'));
        self::assertSame(['01-akodo-toturi', Orientation::Sideways, 2, PersonalHonor::Honored, null, null], [$toturi->code, $toturi->orientation, $toturi->fate, $toturi->personalHonor, $toturi->militarySkill, $toturi->politicalSkill]);
        self::assertSame('01-fine-katana', $this->cardView($view, $scenario->card('katana'))->code);
        $discardPile = $this->stackView($view, $scenario, 'john', 'conflict_discard_pile');
        self::assertSame(['01-banzai', '01-fine-katana'], array_map(fn (CardView $card): ?string => $card->code, $discardPile->cards ?? []));
        self::assertSame(1, $view->rings['fire']->fate);
        self::assertSame($scenario->player('john')->getId(), $view->rings['fire']->claimedById);
        self::assertSame($scenario->player('jane')->getId(), $view->viewerId);
        self::assertSame('draw_phase_action_window', $view->place);
    }

    public function testTheActionsAvailableToTheViewer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('john')
            ->build();

        self::assertContains('pass_opportunity', $this->view($scenario, 'john')->availableActions);
        self::assertNotContains('pass_opportunity', $this->view($scenario, 'jane')->availableActions);
    }
}
