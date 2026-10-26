<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\AccessToken;
use App\Entity\Candidate;
use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\LinkedAccount;
use App\Entity\User;
use App\Enum\OAuthProvider;
use App\Model\UserSettings;
use App\Security\AccountDeleter;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Doctrine\ORM\EntityManagerInterface;

class AccountDeleterTest extends AbstractKernelTestCase
{
    private function user(string $id): User
    {
        $user = self::service(EntityManagerInterface::class)->find(User::class, $id);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function deck(string $id, User $owner): Deck
    {
        $deck = new Deck($id)->setName("Deck {$id}")->setOwner($owner)->setCards(['hida-kisada' => 1]);
        self::service(EntityManagerInterface::class)->persist($deck);

        return $deck;
    }

    public function testTheUserIsAnonymizedAndTheirBotsWithThem(): void
    {
        self::service(AccountDeleter::class)->delete($this->user('john'));

        $john = $this->user('john');
        self::assertSame([User::FORMER_PLAYER_NAME, null, null, null, UserSettings::fromArray([])->toArray()], [$john->getName(), $john->getUsername(), $john->getPassword(), $john->getPreferredClan(), $john->getSettings()->toArray()]);
        self::assertSame(User::FORMER_PLAYER_NAME, $this->user('akodo-bot')->getName());
        self::assertTrue($john->isDeleted());
        self::assertFalse($john->isGuest());
        self::assertTrue($this->user('akodo-bot')->isDeleted());
        self::assertFalse($this->user('jane')->isDeleted());
        self::assertSame('Jane', $this->user('jane')->getName());
    }

    public function testTheTokensAndTheLinkedIdentitiesAreDeleted(): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $entityManager->persist(new LinkedAccount($this->user('john'), OAuthProvider::Google, 'google-john', new \DateTimeImmutable()));
        $entityManager->flush();

        self::service(AccountDeleter::class)->delete($this->user('john'));

        self::assertSame(0, $entityManager->getRepository(LinkedAccount::class)->count(['user' => 'john']));
        self::assertSame(0, $entityManager->getRepository(AccessToken::class)->count(['user' => ['john', 'akodo-bot', 'hida-bot']]));
        self::assertSame(1, $entityManager->getRepository(AccessToken::class)->count(['user' => 'jane']));
    }

    public function testTheDecksAreDeletedButThosePlayedInAGame(): void
    {
        $john = $this->user('john');
        $this->deck('john-played', $john);
        $this->deck('john-unplayed', $john);
        self::service(EntityManagerInterface::class)->flush();
        $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->deck('john-played'))
            ->withPlayer('jane')
            ->persist();

        self::service(AccountDeleter::class)->delete($john);

        self::service(EntityManagerInterface::class)->clear();
        self::assertNull($this->deckRepository->find('john-unplayed'));
        self::assertSame(['john-played'], array_map(fn (Deck $deck): string => $deck->getId(), $this->deckRepository->findBy(['owner' => 'john'])));
        self::assertNull($this->deckRepository->find('john-played')?->getName());
    }

    public function testTheUserLeavesTheQueue(): void
    {
        $john = $this->user('john');
        $candidate = new Candidate()->setUser($john)->setDeck($this->deck('john-queued', $john));
        self::service(EntityManagerInterface::class)->persist($candidate);
        self::service(EntityManagerInterface::class)->flush();

        self::service(AccountDeleter::class)->delete($john);

        self::assertSame(0, self::service(EntityManagerInterface::class)->getRepository(Candidate::class)->count([]));
    }

    public function testTheGamesInProgressAreConcededTheirOpponentsWin(): void
    {
        $game = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('akodo-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->activePlayer('jane')
            ->persist()
            ->game();

        self::service(AccountDeleter::class)->delete($this->user('john'));

        self::assertSame(Game::STATE_ENDED, $game->getCurrentPlace());
        self::assertSame(['akodo-bot concedes the game', 'The game ends', 'jane wins the game: concession'], $this->logLines($game));
    }
}
