<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Exception\Rules\CannotParticipateException;
use App\Exception\Rules\NoAttackerException;
use App\Exception\Rules\NoConflictOpportunityException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\ProvinceNotAttackableException;
use App\Exception\Rules\RingNotUnclaimedException;
use App\Message\DeclareConflict;
use App\MessageHandler\DeclareConflictHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\ConflictState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\GameBuilder;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class DeclareConflictHandlerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): PlayerBuilder|null $john
     * @param \Closure(PlayerBuilder): PlayerBuilder|null $jane
     */
    private function builder(?\Closure $john = null, ?\Closure $jane = null): GameBuilder
    {
        return $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => ($john ?? fn (PlayerBuilder $p): PlayerBuilder => $p)($p
                ->fate(1)
                ->inPlay(['toturi' => '01-akodo-toturi'])
                ->inPlay(['hotaru' => '01-doji-hotaru'], bowed: true)
                ->province('01-entrenched-position', as: 'johns-province')))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => ($jane ?? fn (PlayerBuilder $p): PlayerBuilder => $p)($p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province')))
            ->activePlayer('john');
    }

    /**
     * @param list<string> $attackers
     * @param list<string> $evaded
     */
    private function declare(
        Scenario $scenario,
        array $attackers = ['toturi'],
        string $province = 'province',
        Element $ring = Element::Air,
        ConflictType $type = ConflictType::Military,
        array $evaded = [],
        string $player = 'john',
    ): void {
        self::service(DeclareConflictHandler::class)(new DeclareConflict(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $ring,
            $type,
            $scenario->card($province)->getId(),
            array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $attackers),
            array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $evaded),
        ));
    }

    public function testTheConflictIsDeclared(): void
    {
        $scenario = $this->builder()->ring(Element::Air, fate: 2)->persist();

        $this->declare($scenario, evaded: ['spear']);

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertInstanceOf(ConflictState::class, $state->conflict);
        self::assertSame($scenario->player('john')->getId(), $state->conflict->attackingPlayerId);
        self::assertSame($scenario->player('jane')->getId(), $state->conflict->defendingPlayerId);
        self::assertSame(Element::Air, $state->conflict->ring);
        self::assertSame(ConflictType::Military, $state->conflict->type);
        self::assertSame([$scenario->card('province')->getId()], $state->conflict->attackedProvinceIds);
        self::assertSame([$scenario->card('toturi')->getId()], $state->conflict->attackerIds);
        self::assertSame([$scenario->card('spear')->getId()], $state->conflict->evadedIds);
        self::assertFalse($state->isRingUnclaimed(Element::Air));
        self::assertSame($scenario->player('john')->getId(), $state->lastConflictPlayerId);
        self::assertSame('reveal_attacked_province', $scenario->game()->getCurrentPlace());
    }

    public function testTheAttackingPlayerTakesTheFateOnTheRing(): void
    {
        $scenario = $this->builder()->ring(Element::Air, fate: 2)->persist();

        $this->declare($scenario);

        self::assertSame(3, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        self::assertSame(0, self::service(GameStateManager::class)->getState($scenario->game())->getRing(Element::Air)->fate);
    }

    public function testTheConflictOpportunityOfThatTypeIsUsed(): void
    {
        $scenario = $this->builder()->persist();

        $this->declare($scenario, type: ConflictType::Political);

        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(1, $state->getConflictOpportunities());
        self::assertSame([ConflictType::Political], $state->getDeclaredConflictTypes());
    }

    public function testTheAttackedProvinceIsLeftToTheDefendingPlayerToReveal(): void
    {
        $scenario = $this->builder()->persist();

        $this->declare($scenario);

        self::assertSame(ProvinceCard::STATE_FACEDOWN, $scenario->card('province')->getCurrentPlace());
    }

    public function testTheDefendingPlayerRevealsTheAttackedProvince(): void
    {
        $scenario = $this->builder()->persist();

        // through the bus, so that automatic transitions happen
        $this->messageBus->dispatch(new DeclareConflict(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            Element::Air,
            ConflictType::Military,
            $scenario->card('province')->getId(),
            [$scenario->card('toturi')->getId()],
        ));

        self::assertSame('reveal_attacked_province', $scenario->game()->getCurrentPlace());
        self::assertTrue(self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player('jane')));
    }

    public function testAProvinceAlreadyFaceupIsNotRevealedAgain(): void
    {
        $scenario = $this->builder(jane: fn (PlayerBuilder $p): PlayerBuilder => $p
            ->province('01-entrenched-position', as: 'faceup-province', orientation: Orientation::FaceUp))
            ->persist();

        $this->messageBus->dispatch(new DeclareConflict(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            Element::Air,
            ConflictType::Military,
            $scenario->card('faceup-province')->getId(),
            [$scenario->card('toturi')->getId()],
        ));

        self::assertSame('react_to_conflict_declaration', $scenario->game()->getCurrentPlace());
    }

    public function testRejectsAConflictOfATypeAlreadyDeclared(): void
    {
        $scenario = $this->builder(john: fn (PlayerBuilder $p): PlayerBuilder => $p->declaredConflicts(ConflictType::Military))->persist();

        $this->expectException(NoConflictOpportunityException::class);

        $this->declare($scenario);
    }

    public function testRejectsAClaimedRing(): void
    {
        $scenario = $this->builder()->ring(Element::Air, claimedBy: 'jane')->persist();

        $this->expectException(RingNotUnclaimedException::class);

        $this->declare($scenario);
    }

    public function testRejectsAProvinceOfTheAttackingPlayer(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(ProvinceNotAttackableException::class);

        $this->declare($scenario, province: 'johns-province');
    }

    public function testRejectsABrokenProvince(): void
    {
        $scenario = $this->builder(jane: fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', as: 'broken', orientation: Orientation::UpsideDown))->persist();

        $this->expectException(ProvinceNotAttackableException::class);

        $this->declare($scenario, province: 'broken');
    }

    public function testRejectsTheStrongholdProvinceWhileFewerThanThreeOtherProvincesAreBroken(): void
    {
        $scenario = $this->builder(jane: fn (PlayerBuilder $p): PlayerBuilder => $p
            ->strongholdProvince('01-ancestral-lands', as: 'stronghold-province')
            ->province('01-entrenched-position', orientation: Orientation::UpsideDown)
            ->province('01-entrenched-position', orientation: Orientation::UpsideDown))
            ->persist();

        $this->expectException(ProvinceNotAttackableException::class);

        $this->declare($scenario, province: 'stronghold-province');
    }

    public function testTheStrongholdProvinceCanBeAttackedOnceThreeOtherProvincesAreBroken(): void
    {
        $scenario = $this->builder(jane: fn (PlayerBuilder $p): PlayerBuilder => $p
            ->strongholdProvince('01-ancestral-lands', as: 'stronghold-province')
            ->province('01-entrenched-position', orientation: Orientation::UpsideDown)
            ->province('01-entrenched-position', orientation: Orientation::UpsideDown)
            ->province('01-entrenched-position', orientation: Orientation::UpsideDown))
            ->persist();

        $this->declare($scenario, province: 'stronghold-province');

        self::assertSame('reveal_attacked_province', $scenario->game()->getCurrentPlace());
    }

    public function testRejectsABowedAttacker(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(CannotParticipateException::class);

        $this->declare($scenario, attackers: ['toturi', 'hotaru']);
    }

    public function testRejectsAnAttackerOfAnotherPlayer(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(NotOwnedCardException::class);

        $this->declare($scenario, attackers: ['spear']);
    }

    public function testRejectsAConflictWithoutAttackers(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(NoAttackerException::class);

        $this->declare($scenario, attackers: []);
    }

    public function testRejectsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(NotActivePlayerException::class);

        $this->declare($scenario, attackers: ['spear'], province: 'johns-province', player: 'jane');
    }
}
