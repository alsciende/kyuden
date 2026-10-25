<?php

declare(strict_types=1);

namespace App\Tests\Service\Bot;

use App\Entity\Event;
use App\Enum\Element;
use App\Enum\PersonalHonor;
use App\Message\BowCard;
use App\Message\DiscardRandomCardFromHand;
use App\Message\DishonorCharacter;
use App\Message\DrawConflictCards;
use App\Message\GainHonor;
use App\Message\HonorCharacter;
use App\Message\PassOpportunity;
use App\Message\PlayerActionInterface;
use App\Message\ReadyCard;
use App\Message\RemoveFate;
use App\Service\Bot\RingEffectChooser;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\ORM\EntityManagerInterface;

class RingEffectChooserTest extends AbstractKernelTestCase
{
    private const string BOT = 'kyuden-bot';

    /**
     * The bot won a conflict it declared against the province of john.
     *
     * @param \Closure(PlayerBuilder): PlayerBuilder|null $john
     * @param \Closure(PlayerBuilder): PlayerBuilder|null $bot
     */
    private function scenarioWith(Element $ring, ?\Closure $john = null, ?\Closure $bot = null): Scenario
    {
        return $this->scenario()
            ->atPlace('resolve_ring_effects')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => ($john ?? fn (PlayerBuilder $p): PlayerBuilder => $p)($p->province('01-the-art-of-war', 'province')))
            ->withPlayer(self::BOT, fn (PlayerBuilder $p): PlayerBuilder => ($bot ?? fn (PlayerBuilder $p): PlayerBuilder => $p)($p->inPlay(['attacker' => '01-hida-guardian'], bowed: true)))
            ->conflict(self::BOT, 'province', ['attacker'], ring: $ring)
            ->activePlayer(self::BOT)
            ->persist();
    }

    private function nextAction(Scenario $scenario): ?PlayerActionInterface
    {
        return self::service(RingEffectChooser::class)->nextAction($scenario->game(), $scenario->player(self::BOT), $scenario->player('john'));
    }

    /**
     * Records the messages as events of the game, in order.
     *
     * @param list<array{class-string, string}> $messages type and sender alias
     */
    private function sent(Scenario $scenario, array $messages): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        foreach ($messages as [$type, $sender]) {
            $game = $scenario->game();
            $entityManager->persist(new Event($game, $game->incrementVersion(), $type, (string) json_encode([
                'game_id' => $game->getId(),
                'player_id' => $scenario->player($sender)->getId(),
            ])));
        }
        $entityManager->flush();
    }

    public function testGainsHonorWithTheAirRingOnce(): void
    {
        $scenario = $this->scenarioWith(Element::Air);
        // sent during an earlier step
        $this->sent($scenario, [[GainHonor::class, self::BOT], [PassOpportunity::class, 'john']]);

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(GainHonor::class, $action);
        self::assertSame($scenario->player(self::BOT)->getId(), $action->getTargetPlayerId());
        self::assertSame(RingEffectChooser::AIR_HONOR_GAIN, $action->getHonorGain());

        // a message of the opponent since does not matter
        $this->sent($scenario, [[GainHonor::class, self::BOT], [GainHonor::class, 'john']]);
        self::assertNull($this->nextAction($scenario));
    }

    public function testDrawsThenDiscardsAtRandomWithTheEarthRing(): void
    {
        $scenario = $this->scenarioWith(Element::Earth, fn (PlayerBuilder $p): PlayerBuilder => $p->hand(['01-fine-katana']));

        $draw = $this->nextAction($scenario);
        self::assertInstanceOf(DrawConflictCards::class, $draw);
        self::assertSame($scenario->player(self::BOT)->getId(), $draw->getTargetPlayerId());

        $this->sent($scenario, [[DrawConflictCards::class, self::BOT]]);
        $discard = $this->nextAction($scenario);
        self::assertInstanceOf(DiscardRandomCardFromHand::class, $discard);
        self::assertSame($scenario->player('john')->getId(), $discard->getTargetPlayerId());

        $this->sent($scenario, [[DiscardRandomCardFromHand::class, self::BOT]]);
        self::assertNull($this->nextAction($scenario));
    }

    public function testDoesNotDiscardFromAnEmptyHand(): void
    {
        $scenario = $this->scenarioWith(Element::Earth);
        $this->sent($scenario, [[DrawConflictCards::class, self::BOT]]);

        self::assertNull($this->nextAction($scenario));
    }

    public function testHonorsItsMostGloriousCharacterWithTheFireRing(): void
    {
        $scenario = $this->scenarioWith(
            Element::Fire,
            fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['01-akodo-gunso']),
            fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']),
        );

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(HonorCharacter::class, $action);
        self::assertSame($scenario->card('toturi')->getId(), $action->getCardId());
    }

    public function testDishonorsTheMostGloriousCharacterOfTheOpponentWithTheFireRing(): void
    {
        $scenario = $this->scenarioWith(
            Element::Fire,
            fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']),
            fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['01-akodo-gunso'], personalHonor: PersonalHonor::Honored),
        );

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(DishonorCharacter::class, $action);
        self::assertSame($scenario->card('toturi')->getId(), $action->getCardId());
    }

    public function testBowsTheStrongestCharacterOfTheOpponentWithNoFateWithTheWaterRing(): void
    {
        $scenario = $this->scenarioWith(Element::Water, fn (PlayerBuilder $p): PlayerBuilder => $p
            ->inPlay(['01-akodo-toturi'], fate: 1)
            ->inPlay(['beiona' => '01-matsu-beiona'])
            ->inPlay(['01-akodo-gunso']));

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(BowCard::class, $action);
        self::assertSame($scenario->card('beiona')->getId(), $action->getCardId());
    }

    public function testReadiesItsStrongestCharacterWithTheWaterRingWhenNoneCanBeBowed(): void
    {
        $scenario = $this->scenarioWith(
            Element::Water,
            fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['01-akodo-toturi'], fate: 1),
            fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['kisada' => '01-hida-kisada'], bowed: true),
        );

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(ReadyCard::class, $action);
        self::assertSame($scenario->card('kisada')->getId(), $action->getCardId());
    }

    public function testRemovesTheLastFateOfACharacterOfTheOpponentWithTheVoidRing(): void
    {
        $scenario = $this->scenarioWith(Element::Void, fn (PlayerBuilder $p): PlayerBuilder => $p
            ->inPlay(['01-akodo-toturi'], fate: 2)
            ->inPlay(['gunso' => '01-akodo-gunso'], fate: 1));

        $action = $this->nextAction($scenario);

        self::assertInstanceOf(RemoveFate::class, $action);
        self::assertSame($scenario->card('gunso')->getId(), $action->getCardId());
        self::assertSame(1, $action->getAmount());
    }
}
