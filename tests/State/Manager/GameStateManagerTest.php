<?php

declare(strict_types=1);

namespace App\Tests\State\Manager;

use App\Entity\Game;
use App\Enum\ConflictType;
use App\Enum\Element;
use App\State\Manager\GameStateManager;
use App\State\Model\ConflictState;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;

class GameStateManagerTest extends AbstractKernelTestCase
{
    public function testTheRingsTheConflictAndTheImperialFavorAreKeptInTheState(): void
    {
        $manager = self::service(GameStateManager::class);
        $game = new Game('game', random_bytes(32));
        $manager->withState($game, function (GameState $state): void {
            $state->initRings();
            $state->getRing(Element::Water)->fate = 2;
            $state->getRing(Element::Air)->claimedById = 'jane';
            $state->conflict = new ConflictState('john', 'jane', Element::Fire, ConflictType::Political, 'province');
            $state->conflict->attackedProvinceIds[] = 'other-province';
            $state->conflict->attackerIds = ['kaito'];
            $state->imperialFavorHolderId = 'john';
            $state->imperialFavorSide = ConflictType::Military;
        });

        $state = $manager->getState($game);

        self::assertCount(5, $state->rings);
        self::assertSame(2, $state->getRing(Element::Water)->fate);
        self::assertSame('jane', $state->getRing(Element::Air)->claimedById);
        self::assertInstanceOf(ConflictState::class, $state->conflict);
        self::assertSame('john', $state->conflict->attackingPlayerId);
        self::assertSame(Element::Fire, $state->conflict->ring);
        self::assertSame(ConflictType::Political, $state->conflict->type);
        self::assertSame('province', $state->conflict->provinceId);
        self::assertSame(['province', 'other-province'], $state->conflict->attackedProvinceIds);
        self::assertSame(['kaito'], $state->conflict->attackerIds);
        self::assertSame(ConflictType::Military, $state->imperialFavorSide);
    }
}
