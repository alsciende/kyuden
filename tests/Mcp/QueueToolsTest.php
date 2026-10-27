<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\DataFixtures\AppFixtures;
use App\Entity\Invitation;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Repository\CandidateRepository;
use App\Security\CurrentAccessToken;
use App\Service\Invitation\InvitationIssuer;
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

    public function testTheBotOfAnInvitationIsMatchedWithItsHostOnly(): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $hida = $entityManager->find(User::class, 'hida-bot');
        $jane = $entityManager->find(User::class, 'jane');
        self::assertInstanceOf(User::class, $hida);
        self::assertInstanceOf(User::class, $jane);
        foreach (self::service(CandidateRepository::class)->findAll() as $candidate) {
            $entityManager->remove($candidate);
        }
        $entityManager->flush();
        [$invitation, $key] = self::service(InvitationIssuer::class)->issue($jane, InvitationKind::Bot);
        self::assertIsString($key);
        $bot = $invitation->getBot();
        self::assertInstanceOf(User::class, $bot);
        self::service(MatchMakingQueue::class)->join($hida, AppFixtures::LION_STARTER_DECK);

        $result = $this->callTool('join_queue', ['deck_id' => AppFixtures::CRAB_STARTER_DECK], $key);

        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertTrue($this->isQueued($bot->getId()));
        self::assertTrue($this->isQueued('hida-bot'));

        // the request of the bot is over: its token is forgotten, and the entities were detached
        self::service(CurrentAccessToken::class)->reset();
        $jane = $entityManager->find(User::class, 'jane');
        $invitation = $entityManager->find(Invitation::class, $invitation->getId());
        self::assertInstanceOf(User::class, $jane);
        self::assertInstanceOf(Invitation::class, $invitation);
        self::service(MatchMakingQueue::class)->join($jane, AppFixtures::LION_STARTER_DECK, $invitation);

        self::assertFalse($this->isQueued($bot->getId()));
        $games = $this->gameRepository->findByUser($bot->getId());
        self::assertCount(1, $games);
        self::assertTrue($games[0]->isFriendly());
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
