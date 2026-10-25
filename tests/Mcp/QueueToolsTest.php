<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Repository\CandidateRepository;
use App\Service\MatchMaking\MatchMakingQueue;
use Doctrine\ORM\EntityManagerInterface;

/**
 * list_decks, join_queue and leave_queue.
 */
class QueueToolsTest extends AbstractMcpTestCase
{
    private function isQueued(string $userId): bool
    {
        return self::service(CandidateRepository::class)->count(['user' => $userId]) > 0;
    }

    public function testTheBotListsTheDecksItCanUse(): void
    {
        $result = $this->callTool('list_decks', []);

        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertIsArray($result['structuredContent']);
        $decks = $result['structuredContent']['decks'];
        self::assertIsArray($decks);
        self::assertContains(AppFixtures::CRAB_STARTER_DECK, array_column($decks, 'id'));
        // the starter decks, not the decks of other users
        self::assertSame([null], array_values(array_unique(array_column($decks, 'owner_id'), \SORT_REGULAR)));
    }

    public function testTheBotJoinsAndLeavesTheQueue(): void
    {
        $result = $this->callTool('join_queue', ['deck_id' => AppFixtures::CRAB_STARTER_DECK]);

        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertTrue($this->isQueued('akodo-bot'));

        $result = $this->callTool('leave_queue', []);

        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertFalse($this->isQueued('akodo-bot'));
    }

    public function testTheBotIsMatchedWithTheCandidateWaiting(): void
    {
        $hida = self::service(EntityManagerInterface::class)->find(User::class, 'hida-bot');
        self::assertInstanceOf(User::class, $hida);
        self::service(MatchMakingQueue::class)->join($hida, AppFixtures::LION_STARTER_DECK);

        $this->callTool('join_queue', ['deck_id' => AppFixtures::CRAB_STARTER_DECK]);

        self::assertFalse($this->isQueued('akodo-bot'));
        self::assertFalse($this->isQueued('hida-bot'));
        $games = $this->gameRepository->findByUser('akodo-bot');
        self::assertCount(1, $games);
    }

    public function testTheBotJoinsTheQueueOnce(): void
    {
        $this->callTool('join_queue', ['deck_id' => AppFixtures::CRAB_STARTER_DECK]);

        $result = $this->callTool('join_queue', ['deck_id' => AppFixtures::CRAB_STARTER_DECK]);

        self::assertTrue($result['isError']);
    }

    public function testTheDeckMustBeUsable(): void
    {
        $result = $this->callTool('join_queue', ['deck_id' => 'unknown']);

        self::assertTrue($result['isError']);
        self::assertFalse($this->isQueued('akodo-bot'));
    }

    public function testTheBotCannotLeaveTheQueueItIsNotIn(): void
    {
        $result = $this->callTool('leave_queue', []);

        self::assertTrue($result['isError']);
    }
}
