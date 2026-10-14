<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Game;
use App\Message\PlayerActionInterface;
use App\Service\ActionMessageRegistry;
use App\Service\AvailableActionsResolver;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class AvailableActionsResolverTest extends AbstractKernelTestCase
{
    /**
     * @return list<string>
     */
    private function resolve(Scenario $scenario, string $player): array
    {
        return self::service(AvailableActionsResolver::class)->resolve($scenario->game(), $scenario->player($player));
    }

    public function testEveryPublicMessageHasAHandlerCheckingItsAvailability(): void
    {
        $resolver = self::service(AvailableActionsResolver::class);

        foreach (self::service(ActionMessageRegistry::class)->getPublicMessageClasses() as $messageClass) {
            self::assertTrue(is_subclass_of($messageClass, PlayerActionInterface::class), $messageClass);
            // throws unless the handler implements PlayerActionHandlerInterface
            $resolver->getHandler($messageClass);
        }
    }

    public function testTheActivePlayerCanActInAnActionWindow(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('john')
            ->build();

        $actions = $this->resolve($scenario, 'john');

        foreach (['pass_opportunity', 'end_opportunity', 'trigger_action_ability', 'play_character_from_hand', 'play_attachment', 'play_event', 'initiate_duel', 'bow_card', 'lose_honor'] as $type) {
            self::assertContains($type, $actions);
        }

        foreach (['collect_fate', 'declare_conflict', 'bid_honor', 'move_home', 'end_duel'] as $type) {
            self::assertNotContains($type, $actions);
        }
    }

    public function testAPlayerWithoutTheOpportunityToActCanOnlyManipulateTheBoard(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('john')
            ->build();

        $actions = $this->resolve($scenario, 'jane');

        self::assertContains('bow_card', $actions);
        self::assertNotContains('pass_opportunity', $actions);
        self::assertNotContains('trigger_action_ability', $actions);
    }

    public function testNoCharacterFromHandInTheActionWindowOfTheDynastyPhase(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('john')
            ->build();

        $actions = $this->resolve($scenario, 'john');

        self::assertContains('play_character_from_province', $actions);
        self::assertContains('trigger_action_ability', $actions);
        self::assertNotContains('play_character_from_hand', $actions);
        self::assertNotContains('play_attachment', $actions);
    }

    public function testThePlayersInvolvedInADuelBidBeforeActingAgain(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['spear' => '01-master-of-the-spear']))
            ->activePlayer('john')
            ->duel('john', ['toturi'], ['spear'])
            ->build();

        foreach (['john', 'jane'] as $player) {
            $actions = $this->resolve($scenario, $player);

            self::assertContains('bid_honor', $actions);
            self::assertNotContains('declare_duel_statistic', $actions);
            self::assertNotContains('pass_opportunity', $actions);
            self::assertNotContains('trigger_action_ability', $actions);
        }
    }

    public function testAPlayerWhoHasBidCannotBidAgain(): void
    {
        $scenario = $this->scenario()
            ->atPlace('honor_bid')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->pendingHonorBid(3))
            ->withPlayer('jane')
            ->build();

        self::assertNotContains('bid_honor', $this->resolve($scenario, 'john'));
        self::assertContains('bid_honor', $this->resolve($scenario, 'jane'));
    }

    public function testNothingIsAvailableOnceTheGameHasEnded(): void
    {
        $scenario = $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('john')
            ->withPlayer('jane')
            ->build();

        self::assertSame([], $this->resolve($scenario, 'john'));
    }
}
