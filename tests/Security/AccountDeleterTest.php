<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\AccessToken;
use App\Entity\Candidate;
use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\LinkedAccount;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\OAuthProvider;
use App\Enum\BannerPattern;
use App\Enum\Mon;
use App\Model\Banner;
use App\Model\UserSettings;
use App\Service\Nobori\NoboriCollection;
use App\Service\Trophy\TrophyCase;
use App\Enum\Trophy;
use App\Repository\SpectatorRepository;
use App\Security\AccountDeleter;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class AccountDeleterTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

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

    public function testTheNoboriIsForgottenWithTheTrophiesAndWhatTheUserEarned(): void
    {
        $this->user('john')->changeBanner(new Banner(Mon::Crab, BannerPattern::Seigaiha));
        self::service(TrophyCase::class)->award($this->user('john'), Trophy::ClanLion, new \DateTimeImmutable());
        self::service(EntityManagerInterface::class)->flush();

        self::service(AccountDeleter::class)->delete($this->user('john'));

        $john = $this->user('john');
        self::assertEquals(new Banner(), $john->getBanner());
        self::assertSame([Mon::Enso], self::service(NoboriCollection::class)->mons($john));
        self::assertSame([BannerPattern::None], self::service(NoboriCollection::class)->patterns($john));
        self::assertSame([], self::service(TrophyCase::class)->earned($john));
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

    public function testTheUserNoLongerWatchesAnyGame(): void
    {
        $game = $this->scenario()->atPlace('draw_phase_action_window')->withPlayer('jane')->withPlayer('harry')->persist()->game();
        self::service(SpectatorRepository::class)->see($game->getId(), 'john', new \DateTimeImmutable());

        self::service(AccountDeleter::class)->delete($this->user('john'));

        self::assertSame(0, self::service(SpectatorRepository::class)->count(['user' => 'john']));
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

    public function testTheUserLeavesTheTournamentsTheyCanStillLeaveAndKeepsTheirDecks(): void
    {
        $now = new \DateTimeImmutable('2026-11-18 12:00:00');
        self::mockTime($now);
        $entityManager = self::service(EntityManagerInterface::class);
        $john = $this->user('john');
        $jane = $this->user('jane');
        $open = new Tournament('open', $jane, $now->modify('+2 days'), random_bytes(32), $now);
        // locked by the time: within the hour before its start
        $locked = new Tournament('locked', $jane, $now->modify('+30 minutes'), random_bytes(32), $now);
        $cancelled = new Tournament('cancelled', $jane, $now->modify('+3 days'), random_bytes(32), $now);
        $cancelled->cancel();
        // organized by the user: it goes on
        $organized = new Tournament('organized', $john, $now->modify('+4 days'), random_bytes(32), $now);
        foreach ([$open, $locked, $cancelled, $organized] as $tournament) {
            $entityManager->persist($tournament);
            $entityManager->persist(new TournamentEntry($tournament, $john, $this->deck("john-{$tournament->getId()}", $john), $now));
        }
        $entityManager->flush();

        self::service(AccountDeleter::class)->delete($john);

        $entityManager->clear();
        $entries = $entityManager->getRepository(TournamentEntry::class)->findBy(['user' => 'john']);
        self::assertSame(['locked'], array_map(fn (TournamentEntry $entry): string => $entry->getTournament()->getId(), $entries));
        self::assertSame('open', $entityManager->find(Tournament::class, 'organized')?->getStatus()->value);
        // the deck still registered stays, without its name
        self::assertSame(['john-locked'], array_map(fn (Deck $deck): string => $deck->getId(), $this->deckRepository->findBy(['owner' => 'john'])));
    }
}
