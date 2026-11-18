<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Repository\CandidateRepository;
use App\Repository\TournamentEntryRepository;
use App\Repository\TournamentMatchRepository;
use App\Service\Tournament\Format\TournamentFormats;
use App\View\Model\TournamentEntryView;
use App\View\Model\TournamentMatchView;
use App\View\Model\TournamentRoundView;
use App\View\Model\TournamentSummaryView;
use App\View\Model\TournamentUserView;
use App\View\Model\TournamentView;

/**
 * Builds the views of the tournaments: the list (the number of the players of every tournament in one query), a
 * tournament with its players and the rounds that have begun, the registrations of a user.
 */
final readonly class TournamentViewFactory
{
    public function __construct(
        private TournamentEntryRepository $tournamentEntryRepository,
        private TournamentMatchRepository $tournamentMatchRepository,
        private CandidateRepository $candidateRepository,
        private TournamentFormats $formats,
    ) {
    }

    /**
     * @param list<Tournament> $tournaments
     *
     * @return list<TournamentSummaryView> in the order of the tournaments
     */
    public function createSummaries(array $tournaments): array
    {
        $entrants = $this->tournamentEntryRepository->countByTournament($tournaments);

        return array_map(fn (Tournament $tournament): TournamentSummaryView => $this->summary($tournament, $entrants[$tournament->getId()] ?? 0), $tournaments);
    }

    public function create(Tournament $tournament): TournamentView
    {
        return new TournamentView(
            id: $tournament->getId(),
            startsAt: $tournament->getStartsAt(),
            locksAt: $tournament->getLocksAt(),
            status: $tournament->getStatus(),
            format: $tournament->getFormat(),
            organizer: $this->user($tournament->getOrganizer()),
            maxEntrants: Tournament::MAX_ENTRANTS,
            players: array_map(
                fn (TournamentEntry $entry): TournamentUserView => $this->user($entry->getUser()),
                $this->tournamentEntryRepository->findByTournament($tournament),
            ),
            size: $tournament->getSize(),
            roundCount: $tournament->getSize() === null ? null : $this->formats->get($tournament->getFormat())->getRoundCount($tournament->getSize()),
            currentRound: $tournament->getCurrentRound(),
            joinDeadline: $tournament->getJoinDeadline(),
            winner: $tournament->getWinner() === null ? null : $this->user($tournament->getWinner()->getUser()),
            rounds: $this->rounds($tournament),
        );
    }

    /**
     * @return list<TournamentRoundView>
     */
    private function rounds(Tournament $tournament): array
    {
        if ($tournament->getCurrentRound() === 0) {
            return [];
        }

        $matches = $this->tournamentMatchRepository->findRevealed($tournament);
        $waiting = [];
        foreach ($this->candidateRepository->findWaitingWithMatches($matches) as $candidate) {
            $waiting[(int) $candidate->getMatch()?->getId()][] = $candidate->getUser()->getId();
        }

        $rounds = [];
        foreach ($matches as $match) {
            $rounds[$match->getRound()][] = new TournamentMatchView(
                id: (int) $match->getId(),
                table: $match->getTable(),
                players: array_map(fn (TournamentEntry $entry): TournamentUserView => $this->user($entry->getUser()), $match->getEntries()),
                gameId: $match->getGame()?->getId(),
                winnerUserId: $match->getWinner()?->getUser()->getId(),
                outcome: $match->getOutcome(),
                victoryCondition: $match->getVictoryCondition(),
                waiting: $waiting[(int) $match->getId()] ?? [],
            );
        }

        return array_map(static fn (int $number, array $matches): TournamentRoundView => new TournamentRoundView($number, $matches), array_keys($rounds), $rounds);
    }

    /**
     * @param list<TournamentEntry> $entries with their tournaments and decks
     *
     * @return list<TournamentEntryView> in the order of the registrations
     */
    public function createEntries(array $entries): array
    {
        $tournaments = array_map(static fn (TournamentEntry $entry): Tournament => $entry->getTournament(), $entries);
        $entrants = $this->tournamentEntryRepository->countByTournament($tournaments);

        return array_map(fn (TournamentEntry $entry): TournamentEntryView => new TournamentEntryView(
            tournament: $this->summary($entry->getTournament(), $entrants[$entry->getTournament()->getId()] ?? 0),
            deckId: $entry->getDeck()->getId(),
            deckName: $entry->getDeck()->getName(),
            registeredAt: $entry->getRegisteredAt(),
            eliminated: $entry->isEliminated(),
        ), $entries);
    }

    private function summary(Tournament $tournament, int $entrants): TournamentSummaryView
    {
        return new TournamentSummaryView(
            id: $tournament->getId(),
            startsAt: $tournament->getStartsAt(),
            locksAt: $tournament->getLocksAt(),
            status: $tournament->getStatus(),
            format: $tournament->getFormat(),
            organizer: $this->user($tournament->getOrganizer()),
            entrants: $entrants,
            maxEntrants: Tournament::MAX_ENTRANTS,
        );
    }

    private function user(User $user): TournamentUserView
    {
        return new TournamentUserView($user->getId(), $user->getName(), $user->getBanner());
    }
}
