<?php

declare(strict_types=1);

namespace App\Tests\Service\Bot;

use App\DataFixtures\AppFixtures;
use App\Entity\PhysicalCard;
use App\Message\BidHonor;
use App\Message\ChooseStrongholdProvince;
use App\Message\CollectFate;
use App\Message\DeclareConflictSkill;
use App\Message\DeclareGloryCount;
use App\Message\DeclareNoDefenders;
use App\Message\PassOpportunity;
use App\Message\PlayerActionInterface;
use App\Message\RevealDynastyCard;
use App\Message\RevealProvince;
use App\Service\Bot\BotStrategy;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class BotStrategyTest extends AbstractKernelTestCase
{
    private const string BOT = 'kyuden-bot';

    private function nextAction(Scenario $scenario): ?PlayerActionInterface
    {
        return self::service(BotStrategy::class)->nextAction($scenario->game(), $scenario->player(self::BOT));
    }

    public function testChoosesAStrongholdProvinceDuringSetup(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::LION_STARTER_DECK))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRAB_STARTER_DECK))
            ->build();

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(ChooseStrongholdProvince::class, $action);
        // the province with the first code
        $province = $scenario->player(self::BOT)->getPhysicalCards()->findFirst(fn (int $key, PhysicalCard $card): bool => $card->getId() === $action->getCardId());
        self::assertSame('01-ancestral-lands', $province?->getLogicalCard()->getId());
    }

    public function testRevealsItsFacedownDynastyCardsAtTheBeginningOfTheDynastyPhase(): void
    {
        $build = fn (bool $faceup): Scenario => $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-the-art-of-war', dynasty: '01-akodo-toturi'))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-peace', dynasty: '01-hida-kisada', dynastyAs: 'kisada', dynastyFaceup: $faceup))
            ->build();

        $scenario = $build(false);
        $action = $this->nextAction($scenario);

        self::assertInstanceOf(RevealDynastyCard::class, $action);
        self::assertSame($scenario->card('kisada')->getId(), $action->getCardId());
        // the facedown cards of the opponent are theirs to reveal
        self::assertNull($this->nextAction($build(true)));
    }

    public function testCollectsTheFateOfItsStronghold(): void
    {
        $scenario = $this->scenario()
            ->atPlace('collect_fate')
            ->withPlayer('john')
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p->stronghold('01-yojin-no-shiro'))
            ->activePlayer(self::BOT)
            ->build();

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(CollectFate::class, $action);
        self::assertSame(7, $action->getAmount());
    }

    public function testBidsHonorOnce(): void
    {
        $bidding = $this->scenario()->atPlace('honor_bid')->withPlayer('john')->withPlayer(self::BOT)->build();
        $hasBid = $this->scenario()->atPlace('honor_bid')->withPlayer('john')
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p->pendingHonorBid(3))
            ->build();

        $action = $this->nextAction($bidding);

        self::assertInstanceOf(BidHonor::class, $action);
        self::assertSame(BotStrategy::HONOR_BID, $action->getBid());
        self::assertNull($this->nextAction($hasBid));
    }

    public function testPassesItsOpportunities(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer(self::BOT)
            ->activePlayer(self::BOT)
            ->build();

        self::assertInstanceOf(PassOpportunity::class, $this->nextAction($scenario));
    }

    public function testWaitsWhileTheOpponentHasTheOpportunityToAct(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer(self::BOT)
            ->activePlayer('john')
            ->build();

        self::assertNull($this->nextAction($scenario));
    }

    public function testRevealsTheProvinceItDefends(): void
    {
        $build = fn (string $defender): Scenario => $this->scenario()
            ->atPlace('reveal_attacked_province')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi'])
                ->province('01-the-art-of-war', 'johns-province'))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->province('01-the-art-of-peace', 'bots-province'))
            ->conflict($defender === self::BOT ? 'john' : self::BOT, $defender === self::BOT ? 'bots-province' : 'johns-province', [$defender === self::BOT ? 'toturi' : 'kisada'])
            ->activePlayer($defender)
            ->build();

        $scenario = $build(self::BOT);
        $action = $this->nextAction($scenario);

        self::assertInstanceOf(RevealProvince::class, $action);
        self::assertSame($scenario->card('bots-province')->getId(), $action->getCardId());
        // the province of the opponent is theirs to reveal
        self::assertNull($this->nextAction($build('john')));
    }

    public function testDeclaresNoDefenders(): void
    {
        $scenario = $this->scenario()
            ->atPlace('declare_defenders')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-the-art-of-peace', 'province'))
            ->conflict('john', 'province', ['toturi'])
            ->activePlayer(self::BOT)
            ->build();

        self::assertInstanceOf(DeclareNoDefenders::class, $this->nextAction($scenario));
    }

    public function testDeclaresThePrintedSkillOfItsReadyDefendersOnce(): void
    {
        $build = fn (?int $defenderSkill): Scenario => $this->scenario()
            ->atPlace('determine_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-peace', 'province')
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->inPlay(['beiona' => '01-matsu-beiona'], bowed: true))
            ->conflict('john', 'province', ['toturi'], ['kisada', 'beiona'])
            ->withGameState(function (GameState $state) use ($defenderSkill): void {
                $state->currentConflict()->defenderSkill = $defenderSkill;
            })
            ->build();

        $action = $this->nextAction($build(null));

        self::assertInstanceOf(DeclareConflictSkill::class, $action);
        self::assertSame(7, $action->getSkill());
        self::assertNull($this->nextAction($build(7)));
    }

    public function testDeclaresThePrintedGloryOfItsReadyCharacters(): void
    {
        $scenario = $this->scenario()
            ->atPlace('glory_count')
            ->withPlayer('john')
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['01-akodo-toturi'])
                ->inPlay(['01-matsu-beiona'], bowed: true))
            ->build();

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(DeclareGloryCount::class, $action);
        self::assertSame(3, $action->getGlory());
    }
}
