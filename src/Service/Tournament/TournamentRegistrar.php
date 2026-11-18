<?php

declare(strict_types=1);

namespace App\Service\Tournament;

use App\Entity\Deck;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Exception\Api\NotRegisteredException;
use App\Exception\Api\TournamentClosedException;
use App\Exception\Api\TournamentFullException;
use App\Exception\Data\DeckNotFoundException;
use App\Exception\Data\TournamentNotFoundException;
use App\Repository\DeckRepository;
use App\Repository\TournamentEntryRepository;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Registers the accounts in the tournaments, each with one of the decks they can use (checked no more than for a
 * standard game), and unregisters them: until the tournament locks, one hour before it begins, and up to 64 players.
 * A player changes their deck by registering again. Unregistering stays allowed once the tournament is cancelled. The
 * row of the tournament is locked meanwhile, so that two players registering at once do not pass its limit.
 */
final readonly class TournamentRegistrar
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private TournamentEntryRepository $tournamentEntryRepository,
        private DeckRepository $deckRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{TournamentEntry, bool} the registration, and whether it is new (else its deck changed)
     *
     * @throws TournamentNotFoundException
     * @throws DeckNotFoundException       the deck does not exist, belongs to another user or was deleted
     * @throws TournamentClosedException
     * @throws TournamentFullException
     */
    public function register(string $tournamentId, User $user, string $deckId): array
    {
        if (! $user->canTakePartInTournaments()) {
            throw new \InvalidArgumentException('Only an account registers in a tournament.');
        }

        $deck = $this->deckRepository->findUsable($deckId, $user);
        if (! $deck instanceof Deck) {
            throw new DeckNotFoundException($deckId);
        }

        return $this->entityManager->wrapInTransaction(function () use ($tournamentId, $user, $deck): array {
            $tournament = $this->openTournament($tournamentId);

            $entry = $this->tournamentEntryRepository->findOneBy(['tournament' => $tournament, 'user' => $user]);
            if ($entry instanceof TournamentEntry) {
                $entry->changeDeck($deck);

                return [$entry, false];
            }

            if ($this->tournamentEntryRepository->count(['tournament' => $tournament]) >= Tournament::MAX_ENTRANTS) {
                throw new TournamentFullException();
            }

            $entry = new TournamentEntry($tournament, $user, $deck, $this->clock->now());
            $this->entityManager->persist($entry);

            return [$entry, true];
        });
    }

    /**
     * @throws TournamentNotFoundException
     * @throws NotRegisteredException
     * @throws TournamentClosedException
     */
    public function unregister(string $tournamentId, User $user): void
    {
        $this->entityManager->wrapInTransaction(function () use ($tournamentId, $user): void {
            $tournament = $this->tournamentRepository->findForUpdate($tournamentId) ?? throw new TournamentNotFoundException($tournamentId);
            $entry = $this->tournamentEntryRepository->findOneBy(['tournament' => $tournament, 'user' => $user]);
            if (! $entry instanceof TournamentEntry) {
                throw new NotRegisteredException();
            }
            if (! $tournament->isCancelled() && ! $tournament->isOpenAt($this->clock->now())) {
                throw new TournamentClosedException('The tournament is locked: its players can no longer unregister.');
            }

            $this->entityManager->remove($entry);
        });
    }

    /**
     * The tournament, its row locked, still open to registrations.
     *
     * @throws TournamentNotFoundException
     * @throws TournamentClosedException
     */
    private function openTournament(string $tournamentId): Tournament
    {
        $tournament = $this->tournamentRepository->findForUpdate($tournamentId) ?? throw new TournamentNotFoundException($tournamentId);
        if ($tournament->isCancelled()) {
            throw new TournamentClosedException('The tournament is cancelled.');
        }
        if (! $tournament->isOpenAt($this->clock->now())) {
            throw new TournamentClosedException('The tournament is locked: registrations and decks no longer change.');
        }

        return $tournament;
    }
}
