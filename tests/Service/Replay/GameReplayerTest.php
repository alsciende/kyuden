<?php

declare(strict_types=1);

namespace App\Tests\Service\Replay;

use App\DataFixtures\AppFixtures;
use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\ReplayDivergedException;
use App\Exception\Rules\GameNotEndedException;
use App\Message\ChooseStrongholdProvince;
use App\Message\ConcedeGame;
use App\Message\RevealDynastyCard;
use App\Repository\EventRepository;
use App\Service\Replay\GameReplayer;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class GameReplayerTest extends AbstractKernelTestCase
{
    use PlaysEndedGame;

    private function replayer(): GameReplayer
    {
        return self::service(GameReplayer::class);
    }

    private function player(Game $game, string $userId): Player
    {
        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $userId) {
                return $player;
            }
        }

        self::fail("{$userId} does not play the game.");
    }

    public function testTheReplayRebuildsTheGameUnderAnotherId(): void
    {
        $game = $this->playEndedGame();
        $replayer = $this->replayer();
        $replay = $replayer->start($game, $this->player($game, 'john'));

        do {
            $step = $replayer->proceed($replay);
        } while (! $step->ended);

        $copy = $replayer->getReplayGame($replay);
        self::assertInstanceOf(Game::class, $copy);
        self::assertNotSame($game->getId(), $copy->getId());
        self::assertSame($game->getVersion(), $copy->getVersion());
        self::assertSame(Game::STATE_ENDED, $copy->getCurrentPlace());
        self::assertSame($game->getPhysicalCards()->count(), $copy->getPhysicalCards()->count());
        self::assertNull($replayer->findDivergence($replay));
    }

    public function testTheOriginalReadAgainFromTheDatabaseIsTheSame(): void
    {
        $gameId = $this->playEndedGame()->getId();
        // jsonb does not keep the order of the keys of the params of the log
        self::service(EntityManagerInterface::class)->clear();
        $game = $this->gameRepository->get($gameId);
        $replayer = $this->replayer();
        $replay = $replayer->start($game, $this->player($game, 'john'));

        do {
            $step = $replayer->proceed($replay);
        } while (! $step->ended);

        self::assertNull($replayer->findDivergence($replay));
    }

    public function testTheReplayStopsOnEachActionOfTheViewer(): void
    {
        $game = $this->playEndedGame();
        $replayer = $this->replayer();
        $replay = $replayer->start($game, $this->player($game, 'jane'));

        $stops = [];
        do {
            $step = $replayer->proceed($replay);
            if ($step->pausedOn !== null) {
                $stops[] = $step->pausedOn->getName();
            }
        } while (! $step->ended);

        // jane's own actions, not her joining the game (a message of the platform)
        self::assertSame([ChooseStrongholdProvince::class, ...array_fill(0, 4, RevealDynastyCard::class), ConcedeGame::class], $stops);
    }

    public function testEachStepStartsWhereThePreviousOneStopped(): void
    {
        $game = $this->playEndedGame();
        $replayer = $this->replayer();
        $replay = $replayer->start($game, $this->player($game, 'john'));

        $first = $replayer->proceed($replay);
        $second = $replayer->proceed($replay);

        self::assertSame(0, $first->from);
        // CreateGame, both JoinGame, then john chooses his stronghold province
        self::assertSame(4, $first->to);
        self::assertSame($first->to, $second->from);
        self::assertGreaterThan($second->from, $second->to);
    }

    public function testNobodyIsNotifiedAndNoTaskIsSent(): void
    {
        $game = $this->playEndedGame();
        $tasksBefore = count($this->sentTasks());
        $replayer = $this->replayer();
        $replay = $replayer->start($game, $this->player($game, 'john'));

        do {
            $step = $replayer->proceed($replay);
        } while (! $step->ended);

        self::assertCount($tasksBefore, $this->sentTasks());
    }

    public function testAGameInProgressIsNotReplayed(): void
    {
        $game = $this->createGame() ?? self::fail('The game was not created.');
        $this->createPlayer($game, AppFixtures::LION_STARTER_DECK, 'john');

        $this->expectException(GameNotEndedException::class);
        $this->replayer()->start($game, $this->player($game, 'john'));
    }

    public function testAnEventThatCannotBeReplayedStopsTheReplayAtItsVersion(): void
    {
        $game = $this->playEndedGame();
        // the card the event names no longer exists in the replay
        $event = self::service(EventRepository::class)->findOneBy(['game' => $game, 'name' => RevealDynastyCard::class])
            ?? self::fail('No dynasty card was revealed.');
        $payload = json_decode($event->getPayload(), true, flags: JSON_THROW_ON_ERROR);
        \assert(\is_array($payload));
        $event->setPayload(json_encode(['card_id' => '00000000-0000-4000-8000-000000000000'] + $payload, JSON_THROW_ON_ERROR));
        self::service(EntityManagerInterface::class)->flush();
        $replayer = $this->replayer();
        $replay = $replayer->start($game, $this->player($game, 'john'));

        try {
            do {
                $step = $replayer->proceed($replay);
            } while (! $step->ended);
            self::fail('The replay did not diverge.');
        } catch (ReplayDivergedException $exception) {
            self::assertSame($event->getVersion(), $exception->getVersion());
        }
    }
}
