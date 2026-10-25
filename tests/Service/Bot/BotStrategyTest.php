<?php

declare(strict_types=1);

namespace App\Tests\Service\Bot;

use App\DataFixtures\AppFixtures;
use App\Entity\PhysicalCard;
use App\Enum\ConflictType;
use App\Enum\DuelType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Message\BidHonor;
use App\Message\ChooseStrongholdProvince;
use App\Message\CollectFate;
use App\Message\DeclareConflict;
use App\Message\DeclareConflictSkill;
use App\Message\DeclareDefenders;
use App\Message\DeclareDuelStatistic;
use App\Message\DeclareGloryCount;
use App\Message\DeclareNoDefenders;
use App\Message\EndResolution;
use App\Message\PassConflict;
use App\Message\PassOpportunity;
use App\Message\PlayCharacterFromProvince;
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
            ->ring(Element::Fire, claimedBy: self::BOT)
            ->build();

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(DeclareGloryCount::class, $action);
        // the glory of Toturi, and the ring it claimed
        self::assertSame(4, $action->getGlory());
    }

    public function testPlaysTheMostExpensiveCharacterItCanAffordWithTheFateItWillNotSpend(): void
    {
        $build = fn (int $fate): Scenario => $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate($fate)
                ->province('01-the-art-of-war', dynasty: '01-akodo-toturi', dynastyAs: 'toturi', dynastyFaceup: true)
                ->province('01-the-art-of-peace', dynasty: '01-matsu-beiona', dynastyFaceup: true)
                ->province('01-night-raid', dynasty: '01-hida-guardian', dynastyFaceup: true)
                ->province('01-pilgrimage', dynasty: '01-matsu-berserker'))
            ->activePlayer(self::BOT)
            ->build();

        // 1 fate on Toturi, so that he stays in play, and 1 left for the guardian (cost 1); Beiona (cost 3) is out of reach
        $scenario = $build(7);
        $action = $this->nextAction($scenario);
        self::assertInstanceOf(PlayCharacterFromProvince::class, $action);
        self::assertSame($scenario->card('toturi')->getId(), $action->getCardId());
        self::assertSame([5, 1, 1], [$action->getCardCost(), $action->getFateCost(), $action->getFate()]);

        // nothing left for additional fate
        $action = $this->nextAction($build(5));
        self::assertInstanceOf(PlayCharacterFromProvince::class, $action);
        self::assertSame([5, 0, 0], [$action->getCardCost(), $action->getFateCost(), $action->getFate()]);

        // Beiona and the guardian are paid for, the fate left goes on Toturi
        $action = $this->nextAction($build(12));
        self::assertInstanceOf(PlayCharacterFromProvince::class, $action);
        self::assertSame([5, 3, 3], [$action->getCardCost(), $action->getFateCost(), $action->getFate()]);
    }

    public function testDoesNotPlayACopyOfAUniqueCharacterItHasInPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(10)
                ->inPlay(['01-akodo-toturi'])
                ->province('01-the-art-of-war', dynasty: '01-akodo-toturi', dynastyFaceup: true))
            ->activePlayer(self::BOT)
            ->build();

        self::assertInstanceOf(PassOpportunity::class, $this->nextAction($scenario));
    }

    public function testEndsTheResolutionOfTheCharacterItPlayed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p->acted())
            ->activePlayer(self::BOT)
            ->build();

        self::assertInstanceOf(EndResolution::class, $this->nextAction($scenario));
    }

    public function testDeclaresTheConflictThatBreaksAProvinceWithTheFewestAttackers(): void
    {
        $scenario = $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['01-matsu-beiona'])
                ->inPlay(['01-akodo-toturi'], bowed: true)
                ->strongholdProvince('01-ancestral-lands')
                ->province('01-the-art-of-war', 'war', orientation: Orientation::FaceUp)
                ->province('01-the-art-of-peace', 'peace'))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->inPlay(['01-hida-guardian']))
            ->ring(Element::Fire, fate: 2)
            ->activePlayer(self::BOT)
            ->build();

        $action = $this->nextAction($scenario);

        // Kisada (7) breaks the Art of War (3) against Beiona (3); the Art of Peace is face down: assumed 4
        self::assertInstanceOf(DeclareConflict::class, $action);
        self::assertSame(ConflictType::Military, $action->getType());
        self::assertSame($scenario->card('war')->getId(), $action->getProvinceId());
        self::assertSame([$scenario->card('kisada')->getId()], $action->getAttackerIds());
        // the ring with the most fate
        self::assertSame(Element::Fire, $action->getRing());
    }

    public function testPassesTheConflictsItCannotWin(): void
    {
        $scenario = $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['01-akodo-toturi'])
                ->province('01-the-art-of-war'))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['01-hida-guardian'])
                ->declaredConflicts(ConflictType::Political))
            ->activePlayer(self::BOT)
            ->build();

        self::assertInstanceOf(PassConflict::class, $this->nextAction($scenario));
    }

    /**
     * @return \Generator<string, array{array<string, string>, array<string, string>, string, int, list<string>}>
     */
    public static function defenseProvider(): \Generator
    {
        // to win, a defender needs more skill than Beiona (3)
        yield 'wins' => [['beiona' => '01-matsu-beiona'], ['kisada' => '01-hida-kisada', 'guardian' => '01-hida-guardian'], '01-the-art-of-peace', 1, ['kisada']];
        // 9 against the Art of Peace (4): 6 keeps it from breaking
        yield 'keeps the province' => [['toturi' => '01-akodo-toturi', 'beiona' => '01-matsu-beiona'], ['kisada' => '01-hida-kisada'], '01-the-art-of-peace', 1, ['kisada']];
        yield 'hopeless, with conflicts to declare' => [['toturi' => '01-akodo-toturi', 'beiona' => '01-matsu-beiona'], ['gunso' => '01-akodo-gunso', 'guardian' => '01-hida-guardian'], '01-the-art-of-war', 1, []];
        // its weakest character keeps the conflict from being unopposed
        yield 'hopeless, without conflict left' => [['toturi' => '01-akodo-toturi', 'beiona' => '01-matsu-beiona'], ['gunso' => '01-akodo-gunso', 'guardian' => '01-hida-guardian'], '01-the-art-of-war', 0, ['guardian']];
    }

    /**
     * @param array<string, string> $attackers
     * @param array<string, string> $characters
     * @param list<string>          $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('defenseProvider')]
    public function testDeclaresDefenders(array $attackers, array $characters, string $province, int $conflictOpportunities, array $expected): void
    {
        $builder = $this->scenario()
            ->atPlace('declare_defenders')
            ->withPlayer('john', function (PlayerBuilder $p) use ($attackers): PlayerBuilder {
                foreach ($attackers as $alias => $code) {
                    $p->inPlay([$alias => $code]);
                }

                return $p;
            })
            ->withPlayer(self::BOT, function (PlayerBuilder $p) use ($characters, $province, $conflictOpportunities): PlayerBuilder {
                foreach ($characters as $alias => $code) {
                    $p->inPlay([$alias => $code]);
                }

                return $p->province($province, 'province', orientation: Orientation::FaceUp)->conflictOpportunities($conflictOpportunities);
            })
            ->conflict('john', 'province', array_keys($attackers))
            ->activePlayer(self::BOT);
        $scenario = $builder->build();

        $action = $this->nextAction($scenario);

        if ($expected === []) {
            self::assertInstanceOf(DeclareNoDefenders::class, $action);
        } else {
            self::assertInstanceOf(DeclareDefenders::class, $action);
            self::assertSame(array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $expected), $action->getDefenderIds());
        }
    }

    public function testDeclaresTheSkillOfItsSideWithAttachmentsPersonalHonorAndTheImperialFavor(): void
    {
        $scenario = $this->scenario()
            ->atPlace('determine_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-peace', 'province')
                ->inPlay(['kisada' => '01-hida-kisada', '01-fine-katana'])
                ->inPlay(['beiona' => '01-matsu-beiona'], personalHonor: PersonalHonor::Honored))
            ->conflict('john', 'province', ['toturi'], ['kisada', 'beiona'])
            ->withGameState(function (GameState $state, Scenario $scenario): void {
                $state->imperialFavorHolderId = $scenario->player(self::BOT)->getId();
                $state->imperialFavorSide = ConflictType::Military;
            })
            ->build();

        $action = $this->nextAction($scenario);

        // Kisada 7 + 2 (Fine Katana), Beiona 3 + 2 (honored: her glory), 1 (the Imperial Favor)
        self::assertInstanceOf(DeclareConflictSkill::class, $action);
        self::assertSame(15, $action->getSkill());
    }

    /**
     * @return \Generator<string, array{string, int}>
     */
    public static function duelBidProvider(): \Generator
    {
        // 7 against 6: it wins against the lowest bid with 1
        yield 'ahead' => ['01-hida-kisada', 1];
        // 3 against 6: 5 beats a bid of 1
        yield 'behind' => ['01-matsu-beiona', 5];
        // 1 against 6: it cannot win
        yield 'hopeless' => ['01-hida-guardian', 1];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('duelBidProvider')]
    public function testBidsHonorInADuel(string $duelist, int $bid): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['duelist' => $duelist]))
            ->duel('john', ['toturi'], ['duelist'])
            ->build();

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(BidHonor::class, $action);
        self::assertSame($bid, $action->getBid());
    }

    public function testDeclaresItsDuelStatistic(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['beiona' => '01-matsu-beiona']))
            ->duel('john', ['toturi'], ['beiona'], DuelType::Glory, revealed: true)
            ->build();

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(DeclareDuelStatistic::class, $action);
        self::assertSame(2, $action->getStatistic());
    }
}
