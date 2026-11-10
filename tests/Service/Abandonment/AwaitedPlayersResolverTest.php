<?php

declare(strict_types=1);

namespace App\Tests\Service\Abandonment;

use App\Enum\Orientation;
use App\Entity\Player;
use App\Enum\AbilityTiming;
use App\Service\Abandonment\AwaitedPlayersResolver;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class AwaitedPlayersResolverTest extends AbstractKernelTestCase
{
    /**
     * @return list<string> the aliases of the players the game waits for
     */
    private function awaited(Scenario $scenario): array
    {
        return array_map(
            fn (Player $player): string => $player->getUserId(),
            self::service(AwaitedPlayersResolver::class)->resolve($scenario->game()),
        );
    }

    public function testTheGameWaitsForTheActivePlayer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->build();

        self::assertSame(['jane'], $this->awaited($scenario));
    }

    public function testTheGameWaitsForThePlayerWhoResolvesWhatTheyPlayed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->resolving(AbilityTiming::Reaction))
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->build();

        self::assertSame(['john', 'jane'], $this->awaited($scenario));
    }

    public function testTheGameWaitsForThePlayersWhoHaveNotBid(): void
    {
        $scenario = $this->scenario()
            ->atPlace('honor_bid')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->pendingHonorBid(2))
            ->withPlayer('jane')
            ->build();

        self::assertSame(['jane'], $this->awaited($scenario));
    }

    public function testDuringADuelTheGameWaitsForTheBidsOfItsPlayers(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi'])->pendingHonorBid(1))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['spear' => '01-master-of-the-spear']))
            ->duel('john', ['toturi'], ['spear'])
            ->activePlayer('john')
            ->build();

        self::assertSame(['jane'], $this->awaited($scenario));
    }

    public function testTheGameWaitsForTheDefenderToRevealTheAttackedProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_attacked_province')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->build();

        self::assertSame(['jane'], $this->awaited($scenario));
    }

    public function testTheGameWaitsForNobodyBeforeItStarts(): void
    {
        $scenario = $this->scenario()->atPlace('join_game')->withPlayer('john')->build();

        self::assertSame([], $this->awaited($scenario));
    }

    public function testTheGameWaitsForThePlayerWhoMustRevealTheProvinceTheConflictIsMovedTo(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->acted()
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceDown))
            ->conflict('john', 'province', attackers: ['toturi'])
            ->activePlayer('john')
            ->build();

        self::assertSame(['john'], $this->awaited($scenario));
    }
}
