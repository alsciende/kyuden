<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Message\BidHonor;
use App\Message\ClaimImperialFavor;
use App\Message\DeclareConflict;
use App\Message\DeclareConflictSkill;
use App\Message\DeclareDefenders;
use App\Message\DeclareGloryCount;
use App\Message\EndResolution;
use App\Message\PassConflict;
use App\Message\PassOpportunity;
use App\Message\PlayCharacterFromProvince;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * A whole round played through the message bus with simple cards, from the play cards from provinces step to the
 * next round. Safety net for the chaining of the steps; each step is tested in detail elsewhere.
 */
class RoundFlowTest extends AbstractKernelTestCase
{
    private Scenario $scenario;

    private function dispatch(object $message): void
    {
        $this->messageBus->dispatch($message);
    }

    private function gameId(): string
    {
        return $this->scenario->game()->getId();
    }

    private function id(string $alias): string
    {
        return $this->scenario->player($alias)->getId();
    }

    private function card(string $alias): string
    {
        return $this->scenario->card($alias)->getId();
    }

    private function pass(string ...$players): void
    {
        foreach ($players as $player) {
            $this->dispatch(new PassOpportunity($this->gameId(), $this->id($player)));
        }
    }

    private function assertPlace(string $place): void
    {
        self::assertSame($place, $this->scenario->game()->getCurrentPlace());
    }

    private function gameState(): GameState
    {
        return self::service(GameStateManager::class)->getState($this->scenario->game());
    }

    private function playFromProvince(string $player, string $character): void
    {
        $cost = $this->scenario->card($character)->getLogicalCard()->getCost() ?? 0;
        $this->dispatch(new PlayCharacterFromProvince($this->gameId(), $this->id($player), $this->card($character), $cost, 1, 1));
        $this->dispatch(new EndResolution($this->gameId(), $this->id($player)));
    }

    public function testARoundWithAConflict(): void
    {
        $deck = ['01-fine-katana', '01-banzai', '01-fine-katana', '01-banzai'];
        $this->scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->fate(10)
                ->stronghold('01-yojin-no-shiro')
                ->strongholdProvince('01-ancestral-lands')
                ->province('01-entrenched-position', as: 'johns-province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi', dynastyFaceup: true)
                ->dynastyDeck(['01-imperial-storehouse', '01-imperial-storehouse'])
                ->conflictDeck($deck))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->fate(10)
                ->stronghold('01-yojin-no-shiro')
                ->strongholdProvince('01-ancestral-lands')
                ->province('01-entrenched-position', as: 'janes-province', dynasty: '01-doji-hotaru', dynastyAs: 'hotaru', dynastyFaceup: true)
                ->dynastyDeck(['01-imperial-storehouse', '01-imperial-storehouse'])
                ->conflictDeck($deck))
            ->activePlayer('john')
            ->persist();

        // dynasty phase: each player plays a character, then passes
        $this->playFromProvince('john', 'toturi');
        $this->playFromProvince('jane', 'hotaru');
        $this->pass('john', 'jane');

        // draw phase
        $this->assertPlace('honor_bid');
        $this->dispatch(new BidHonor($this->gameId(), $this->id('john'), 1));
        $this->dispatch(new BidHonor($this->gameId(), $this->id('jane'), 1));
        $this->pass('john', 'jane');
        $this->assertPlace('draw_phase_action_window');
        $this->pass('john', 'jane');

        // conflict phase: john attacks
        $this->assertPlace('conflict_phase_action_window');
        $this->pass('john', 'jane');
        $this->assertPlace('declare_conflict');
        $this->dispatch(new DeclareConflict($this->gameId(), $this->id('john'), Element::Water, ConflictType::Military, $this->card('janes-province'), [$this->card('toturi')]));
        $this->pass('john', 'jane');
        $this->dispatch(new DeclareDefenders($this->gameId(), $this->id('jane'), [$this->card('hotaru')]));
        $this->pass('jane', 'john');
        $this->dispatch(new DeclareConflictSkill($this->gameId(), $this->id('john'), 2));
        $this->dispatch(new DeclareConflictSkill($this->gameId(), $this->id('jane'), 3));
        $this->pass('john', 'jane');
        // jane won as the defending player: no ring effect, she claims the ring
        $this->assertPlace('react_to_broken_province');
        $this->pass('john', 'jane');
        $this->assertPlace('react_to_ring_claim');
        $this->pass('john', 'jane');
        self::assertSame($this->id('jane'), $this->gameState()->getRing(Element::Water)->claimedById);

        // the other conflict opportunities are passed
        $this->assertPlace('conflict_phase_action_window');
        $this->pass('john', 'jane');
        self::assertSame([$this->id('jane')], $this->gameState()->activePlayerIds);
        $this->dispatch(new PassConflict($this->gameId(), $this->id('jane')));
        $this->pass('john', 'jane');
        $this->dispatch(new PassConflict($this->gameId(), $this->id('john')));
        $this->pass('john', 'jane');
        $this->dispatch(new PassConflict($this->gameId(), $this->id('jane')));
        $this->pass('john', 'jane');

        // Imperial Favor: tie on glory, the claimed ring makes jane win
        $this->assertPlace('glory_count');
        $this->dispatch(new DeclareGloryCount($this->gameId(), $this->id('john'), 0));
        $this->dispatch(new DeclareGloryCount($this->gameId(), $this->id('jane'), 0));
        $this->assertPlace('claim_imperial_favor');
        $this->dispatch(new ClaimImperialFavor($this->gameId(), $this->id('jane'), ConflictType::Military));

        // fate phase: the characters had 1 fate each, they lose it and stay in play
        $this->assertPlace('fate_phase_action_window');
        $this->pass('john', 'jane');
        $this->assertPlace('discard_from_provinces');
        $this->pass('john', 'jane');

        // next round: the refilled provinces have facedown cards to reveal
        $this->assertPlace('reveal_facedown_dynasty_cards');
        $state = $this->gameState();
        self::assertSame($this->id('jane'), $state->firstPlayerId);
        self::assertSame($this->id('jane'), $state->imperialFavorHolderId);
        self::assertTrue($state->isRingUnclaimed(Element::Water));
        self::assertSame(1, $state->getRing(Element::Air)->fate);
        self::assertSame(DynastyCard::STATE_IN_PLAY, $this->scenario->card('toturi')->getCurrentPlace());
        self::assertSame(Orientation::FaceUp, $this->scenario->card('toturi')->getOrientation());
        self::assertSame(ProvinceCard::STATE_UNBROKEN, $this->scenario->card('janes-province')->getCurrentPlace());

        // the log of the round, as jane reads it
        $log = $this->logLines($this->scenario->game(), $this->scenario->player('jane'));
        self::assertSame('john spends 6 fate', $log[0]);
        self::assertContains('john bids ? honor', $log);
        self::assertContains('jane bids 1 honor', $log);
        self::assertContains('john draws a hidden card', $log);
        self::assertContains('jane draws Banzai!', $log);
        $declaration = array_search('john declares a military conflict against jane for the water ring, attacking Entrenched Position with Akodo Toturi', $log, true);
        self::assertIsInt($declaration);
        self::assertSame([
            'Entrenched Position is revealed',
            'The players may react to the conflict declaration',
            'john passes',
            'jane passes',
            'The reactions to the conflict declaration are over',
            'jane declares Doji Hotaru as defenders',
            'The action window of the conflict opens',
        ], array_slice($log, $declaration + 1, 7));
        self::assertContains('jane wins the conflict (2 against 3)', $log);
        self::assertContains('jane claims the water ring', $log);
        self::assertContains('jane claims the Imperial Favor, military side up', $log);
        self::assertSame(['jane is the first player', 'The fate phase ends', 'The dynasty phase begins'], array_slice($log, -3));
    }
}
