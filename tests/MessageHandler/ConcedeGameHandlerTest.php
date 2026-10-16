<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Event;
use App\Entity\Game;
use App\Enum\VictoryCondition;
use App\Exception\Rules\GameNotStartedException;
use App\Message\ConcedeGame;
use App\MessageHandler\ConcedeGameHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\ORM\EntityManagerInterface;

class ConcedeGameHandlerTest extends AbstractKernelTestCase
{
    private function scenarioInProgress(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->activePlayer('jane')
            ->persist();
    }

    public function testThePlayerWhoConcedesIsEliminated(): void
    {
        $scenario = $this->scenarioInProgress();

        self::service(ConcedeGameHandler::class)(new ConcedeGame($scenario->game()->getId(), $scenario->player('john')->getId()));

        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->isConceded());
        self::assertSame(['john concedes the game'], $this->logLines($scenario->game()));
    }

    public function testTheOpponentWinsAtOnceWhoeverHasTheOpportunityToAct(): void
    {
        $scenario = $this->scenarioInProgress();

        $this->messageBus->dispatch(new ConcedeGame($scenario->game()->getId(), $scenario->player('john')->getId(), 'The engine refuses my attack.'));

        $game = $scenario->game();
        self::assertSame(Game::STATE_ENDED, $game->getCurrentPlace());
        $state = self::service(GameStateManager::class)->getState($game);
        self::assertSame($scenario->player('jane')->getId(), $state->winnerId);
        self::assertSame(VictoryCondition::Concession, $state->victoryCondition);
        self::assertSame(['john concedes the game', 'The game ends', 'jane wins the game: concession'], $this->logLines($game));
    }

    public function testTheReasonIsKeptInTheRecordedEventOnly(): void
    {
        $scenario = $this->scenarioInProgress();

        $this->messageBus->dispatch(new ConcedeGame($scenario->game()->getId(), $scenario->player('john')->getId(), 'The engine refuses my attack.'));

        $events = self::service(EntityManagerInterface::class)->getRepository(Event::class)->findBy(['game' => $scenario->game()]);
        self::assertCount(1, $events);
        self::assertStringContainsString('The engine refuses my attack.', $events[0]->getPayload());
        foreach ($this->logLines($scenario->game(), $scenario->player('jane')) as $line) {
            self::assertStringNotContainsString('attack', $line);
        }
    }

    public function testAPlayerConcedesWhileChoosingTheirStrongholdProvince(): void
    {
        $scenario = $this->scenario()->atPlace('choose_stronghold')->withPlayer('john')->withPlayer('jane')->persist();

        $this->messageBus->dispatch(new ConcedeGame($scenario->game()->getId(), $scenario->player('jane')->getId()));

        self::assertSame(Game::STATE_ENDED, $scenario->game()->getCurrentPlace());
        self::assertSame($scenario->player('john')->getId(), self::service(GameStateManager::class)->getState($scenario->game())->winnerId);
    }

    public function testAPlayerCannotConcedeBeforeTheGameStarts(): void
    {
        $scenario = $this->scenario()->atPlace('join_game')->withPlayer('john')->persist();

        $this->expectException(GameNotStartedException::class);

        self::service(ConcedeGameHandler::class)(new ConcedeGame($scenario->game()->getId(), $scenario->player('john')->getId()));
    }
}
