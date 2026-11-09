<?php

declare(strict_types=1);

namespace App\Tests\Service\Replay;

use App\DataFixtures\AppFixtures;
use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\ReplayDivergedException;
use App\Exception\Rules\GameNotEndedException;
use App\Exception\Rules\ReplayReadOnlyException;
use App\Message\ConcedeGame;
use App\Message\RevealDynastyCard;
use App\Model\GameReplay;
use App\Repository\EventRepository;
use App\Service\AvailableActionsResolver;
use App\Service\Replay\GameReplayer;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The game of PlaysEndedGame has 14 events: 1 CreateGame, 2 and 3 john and jane join, 4 and 5 they choose their
 * stronghold provinces, 6 to 9 john reveals his dynasty cards, 10 to 13 jane hers, 14 jane concedes.
 */
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

    private function replayToTheEnd(GameReplay $replay): void
    {
        do {
            $step = $this->replayer()->proceed($replay);
        } while (! $step->ended);
    }

    public function testTheReplayRebuildsTheGameUnderAnotherId(): void
    {
        $game = $this->playEndedGame();
        $replayer = $this->replayer();
        $replay = $replayer->start($game, $this->player($game, 'john'));

        $this->replayToTheEnd($replay);

        $copy = $replayer->getReplayGame($replay);
        self::assertInstanceOf(Game::class, $copy);
        self::assertNotSame($game->getId(), $copy->getId());
        self::assertSame(14, $copy->getVersion());
        self::assertSame(Game::STATE_ENDED, $copy->getCurrentPlace());
        self::assertSame($game->getPhysicalCards()->count(), $copy->getPhysicalCards()->count());
        self::assertNull($replayer->findDivergence($replay));
    }

    public function testTheCopyIsTheReplayOfTheGameForTheUser(): void
    {
        $game = $this->playEndedGame();
        $replay = $this->replayer()->start($game, $this->player($game, 'john'));

        $this->replayer()->proceed($replay);

        $copy = $this->replayer()->getReplayGame($replay);
        self::assertInstanceOf(Game::class, $copy);
        self::assertTrue($copy->isReplay());
        self::assertSame($game, $copy->getReplayOf());
        self::assertSame('john', $copy->getReplayUser()?->getId());
        // listed nowhere: neither john nor jane find it among their games
        foreach (['john', 'jane'] as $userId) {
            self::assertSame([$game->getId()], array_map(fn (Game $game): string => $game->getId(), $this->gameRepository->findByUser($userId)));
        }
        self::assertTrue($copy->isHiddenFrom('jane'));
        self::assertFalse($copy->isHiddenFrom('john'));
    }

    public function testTheOriginalReadAgainFromTheDatabaseIsTheSame(): void
    {
        $gameId = $this->playEndedGame()->getId();
        // jsonb does not keep the order of the keys of the params of the log
        self::service(EntityManagerInterface::class)->clear();
        $game = $this->gameRepository->get($gameId);
        $replay = $this->replayer()->start($game, $this->player($game, 'john'));

        $this->replayToTheEnd($replay);

        self::assertNull($this->replayer()->findDivergence($replay));
    }

    public function testTheReplayStopsBeforeEachActionOfTheViewer(): void
    {
        $game = $this->playEndedGame();
        $replay = $this->replayer()->start($game, $this->player($game, 'jane'));

        $stops = [];
        do {
            $step = $this->replayer()->proceed($replay);
            if (! $step->ended) {
                self::assertNotNull($step->next);
                self::assertSame($step->to + 1, $step->next->getVersion());
                $stops[] = $step->next->getVersion();
            }
        } while (! $step->ended);

        // her stronghold province, her 4 dynasty cards, her concession; not her joining the game (the platform's)
        self::assertSame([5, 10, 11, 12, 13, 14], $stops);
        self::assertNull($step->next);
    }

    public function testEachStepPlaysTheActionTheReplayStoppedBefore(): void
    {
        $game = $this->playEndedGame();
        $replay = $this->replayer()->start($game, $this->player($game, 'john'));

        $first = $this->replayer()->proceed($replay);
        $second = $this->replayer()->proceed($replay);

        self::assertSame([0, 3], [$first->from, $first->to]);
        // john chooses his stronghold province, then jane hers, up to his first dynasty card
        self::assertSame([3, 5], [$second->from, $second->to]);
        self::assertSame(6, $second->next?->getVersion());
    }

    public function testTheReplayGoesUpToAVersionWhoeverSendsTheEvents(): void
    {
        $game = $this->playEndedGame();
        $replay = $this->replayer()->start($game, $this->player($game, 'john'));

        $step = $this->replayer()->proceed($replay, 11);

        self::assertSame(11, $step->to);
        // john sends nothing after his dynasty cards
        self::assertNull($step->next);
        self::assertFalse($step->ended);
    }

    public function testTheReplayDoesNotGoBack(): void
    {
        $game = $this->playEndedGame();
        $replay = $this->replayer()->start($game, $this->player($game, 'john'));
        $this->replayer()->proceed($replay, 8);

        $this->expectException(\InvalidArgumentException::class);
        $this->replayer()->proceed($replay, 7);
    }

    public function testTheReplayIsFoundAgainWhereItStands(): void
    {
        $game = $this->playEndedGame();
        $replay = $this->replayer()->start($game, $this->player($game, 'john'));
        $this->replayer()->proceed($replay);
        self::service(EntityManagerInterface::class)->clear();
        $game = $this->gameRepository->get($game->getId());

        $found = $this->replayer()->find($game, $this->player($game, 'john'));

        self::assertInstanceOf(GameReplay::class, $found);
        self::assertSame($replay->getReplayGameId(), $found->getReplayGameId());
        $step = $this->replayer()->getStep($found);
        self::assertSame([3, 3, 4], [$step->from, $step->to, $step->next?->getVersion()]);
        self::assertNull($this->replayer()->find($game, $this->player($game, 'jane')));
    }

    public function testStartingAgainDeletesThePreviousReplay(): void
    {
        $game = $this->playEndedGame();
        $previous = $this->replayer()->start($game, $this->player($game, 'john'));
        $this->replayer()->proceed($previous);

        $replay = $this->replayer()->start($game, $this->player($game, 'john'));
        $this->replayer()->proceed($replay);

        self::service(EntityManagerInterface::class)->clear();
        self::assertNull($this->gameRepository->find($previous->getReplayGameId()));
        self::assertInstanceOf(Game::class, $this->gameRepository->find($replay->getReplayGameId()));
    }

    public function testNoOtherMessageIsAcceptedOnTheCopy(): void
    {
        $game = $this->playEndedGame();
        $replay = $this->replayer()->start($game, $this->player($game, 'john'));
        $this->replayer()->proceed($replay);
        $copy = $this->replayer()->getReplayGame($replay) ?? self::fail('No copy.');

        self::assertSame([], self::service(AvailableActionsResolver::class)->resolve($copy, $copy->getPlayer($replay->getReplayViewerId())));
        $this->expectException(ReplayReadOnlyException::class);
        $this->messageBus->dispatch(new ConcedeGame($copy->getId(), $replay->getReplayViewerId()));
    }

    public function testNoTaskIsSent(): void
    {
        $game = $this->playEndedGame();
        $tasksBefore = count($this->sentTasks());

        $this->replayToTheEnd($this->replayer()->start($game, $this->player($game, 'john')));

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
        $replay = $this->replayer()->start($game, $this->player($game, 'john'));

        try {
            $this->replayToTheEnd($replay);
            self::fail('The replay did not diverge.');
        } catch (ReplayDivergedException $exception) {
            self::assertSame($event->getVersion(), $exception->getVersion());
        }
    }
}
