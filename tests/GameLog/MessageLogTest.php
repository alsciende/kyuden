<?php

declare(strict_types=1);

namespace App\Tests\GameLog;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Message\ChooseStrongholdProvince;
use App\Message\DeclareConflict;
use App\Message\DeclareProvinceStrength;
use App\Message\DrawConflictCards;
use App\Message\GainHonor;
use App\Message\HonorCharacter;
use App\Message\MoveHome;
use App\Message\MoveToConflict;
use App\Message\PlayActionEvent;
use App\Message\PlayCharacterFromHand;
use App\Message\RemoveFate;
use App\Message\ResetCharacterSkill;
use App\Message\SetCharacterSkill;
use App\Message\SetHonor;
use App\Message\SetHonorBid;
use App\Message\ShuffleConflictDeck;
use App\Message\TransferHonor;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The entries the messages write in the log of the game, as the opponent of the sender reads them.
 */
class MessageLogTest extends AbstractKernelTestCase
{
    private function scenarioInActionWindow(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->fate(5)
                ->honorBid(1)
                ->hand(['banzai' => '01-banzai'])
                ->conflictDiscardPile(['katana' => '01-fine-katana'])
                ->inPlay(['toturi' => '01-akodo-toturi'], fate: 1))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->conflictDeck(['01-banzai', '01-banzai']))
            ->activePlayer('john')
            ->persist();
    }

    /**
     * @return list<string>
     */
    private function janesLog(Scenario $scenario): array
    {
        return $this->logLines($scenario->game(), $scenario->player('jane'));
    }

    public function testTheHonorOfThePlayers(): void
    {
        $scenario = $this->scenarioInActionWindow();
        [$gameId, $john, $jane] = [$scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('jane')->getId()];

        $this->messageBus->dispatch(new GainHonor($gameId, $jane, $john, 2));
        $this->messageBus->dispatch(new TransferHonor($gameId, $john, $john, $jane, 1));
        $this->messageBus->dispatch(new SetHonorBid($gameId, $jane, $john, 3));

        self::assertSame(['john gains 2 honor', 'john gives 1 honor to jane', 'The honor bid of john is now 3'], $this->janesLog($scenario));
    }

    public function testTheTokensOfACharacter(): void
    {
        $scenario = $this->scenarioInActionWindow();
        [$gameId, $john, $toturi] = [$scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('toturi')->getId()];

        $this->messageBus->dispatch(new HonorCharacter($gameId, $john, $toturi));
        $this->messageBus->dispatch(new RemoveFate($gameId, $john, $toturi, 1));

        self::assertSame(['Akodo Toturi is now honored', '1 fate is removed from Akodo Toturi'], $this->janesLog($scenario));
    }

    public function testTheSkillsOfACharacter(): void
    {
        $scenario = $this->scenarioInActionWindow();
        [$gameId, $jane, $toturi] = [$scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('toturi')->getId()];

        $this->messageBus->dispatch(new SetCharacterSkill($gameId, $jane, $toturi, ConflictType::Military, 4));
        $this->messageBus->dispatch(new ResetCharacterSkill($gameId, $jane, $toturi));

        self::assertSame(['Akodo Toturi now has 4 military skill', 'Akodo Toturi is back to its printed skills'], $this->janesLog($scenario));
    }

    public function testAnEventPlayedFromHandIsRevealed(): void
    {
        $scenario = $this->scenarioInActionWindow();

        $this->messageBus->dispatch(new PlayActionEvent($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('banzai')->getId(), 1));

        self::assertSame(['john spends 1 fate', 'john plays Banzai!', "Banzai! is discarded from john's hand"], $this->janesLog($scenario));
    }

    public function testAPlayerRunningOutOfCardsLosesHonorAndShufflesTheirDiscardPile(): void
    {
        $scenario = $this->scenarioInActionWindow();
        $john = $scenario->player('john')->getId();

        $this->messageBus->dispatch(new DrawConflictCards($scenario->game()->getId(), $john, $john, 1));

        self::assertSame([
            'john loses 5 honor',
            "Fine Katana is put from john's conflict discard pile into john's conflict deck",
            "john's conflict deck is shuffled",
            'john draws a hidden card',
        ], $this->janesLog($scenario));
    }

    public function testShufflingADeck(): void
    {
        $scenario = $this->scenarioInActionWindow();

        $this->messageBus->dispatch(new ShuffleConflictDeck($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('jane')->getId()));

        self::assertSame(["jane's conflict deck is shuffled"], $this->janesLog($scenario));
    }

    public function testTheStrongholdProvinceAndTheStartingHonorDuringTheSetup(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->strongholdProvince('01-entrenched-position', as: 'province', chosen: false))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->strongholdProvince('01-entrenched-position', chosen: false))
            ->persist();
        [$gameId, $john] = [$scenario->game()->getId(), $scenario->player('john')->getId()];

        $this->messageBus->dispatch(new ChooseStrongholdProvince($gameId, $john, $scenario->card('province')->getId()));
        $this->messageBus->dispatch(new SetHonor($gameId, $john, $john, 11));

        self::assertSame(['john chooses a hidden card as their stronghold province', 'The honor of john is set to 11'], $this->janesLog($scenario));
        self::assertSame('john chooses Entrenched Position as their stronghold province', $this->logLines($scenario->game(), $scenario->player('john'))[0]);
    }

    private function scenarioInConflict(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->hand(['spear' => '01-master-of-the-spear'])
                ->inPlay(['toturi' => '01-akodo-toturi'])
                ->inPlay(['hotaru' => '01-doji-hotaru']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['toturi'])
            ->activePlayer('john')
            ->persist();
    }

    public function testTheParticipantsOfAConflict(): void
    {
        $scenario = $this->scenarioInConflict();
        [$gameId, $john] = [$scenario->game()->getId(), $scenario->player('john')->getId()];

        $this->messageBus->dispatch(new MoveToConflict($gameId, $john, $scenario->card('hotaru')->getId()));
        $this->messageBus->dispatch(new MoveHome($gameId, $john, $scenario->card('toturi')->getId()));
        $this->messageBus->dispatch(new PlayCharacterFromHand($gameId, $john, $scenario->card('spear')->getId(), 2, 1, 1, true));

        self::assertSame([
            'Doji Hotaru moves to the conflict',
            'Akodo Toturi moves home',
            'john spends 3 fate',
            "john plays Master of the Spear from john's hand into john's play area",
            '1 fate is placed on Master of the Spear',
            'Master of the Spear joins the conflict',
        ], $this->janesLog($scenario));
    }

    public function testDeclaringTheStrengthOfAProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('determine_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['toturi'])
            ->persist();

        $this->messageBus->dispatch(new DeclareProvinceStrength($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('province')->getId(), 4));

        self::assertContains('jane declares a strength of 4 for Entrenched Position', $this->janesLog($scenario));
    }

    public function testDeclaringAConflict(): void
    {
        $scenario = $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->conflictOpportunities(2)->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province')
                ->inPlay(['hotaru' => '01-doji-hotaru']))
            ->ring(Element::Fire, fate: 2)
            ->activePlayer('john')
            ->persist();

        $this->messageBus->dispatch(new DeclareConflict(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            Element::Fire,
            ConflictType::Political,
            $scenario->card('province')->getId(),
            [$scenario->card('toturi')->getId()],
            [$scenario->card('hotaru')->getId()],
        ));

        self::assertSame([
            'john declares a political conflict against jane for the fire ring, attacking a facedown card with Akodo Toturi',
            'Doji Hotaru are evaded by covert',
            'john takes 2 fate from the fire ring',
            'Entrenched Position is revealed',
            'The players may react to the conflict declaration',
        ], array_slice($this->logLines($scenario->game(), $scenario->player('john')), 0, 5));
    }
}
