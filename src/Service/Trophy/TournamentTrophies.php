<?php

declare(strict_types=1);

namespace App\Service\Trophy;

use App\Entity\Game;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Enum\TournamentStatus;
use App\Enum\Trophy;
use App\Repository\TournamentEntryRepository;
use App\Repository\TournamentMatchRepository;
use App\Trophy\TournamentTrophyInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The trophies the players of a tournament that has ended earn in it: each trophy has its rule (`src/Trophy`, a
 * TournamentTrophyInterface), read from what is stored, so that the tournaments played before earn them too. A
 * deleted account earns nothing. A trophy is earned in the last match the player played, in its game (none for a
 * match won by forfeit or by a bye), so that the end of the final announces the win of the tournament.
 */
readonly class TournamentTrophies
{
    /**
     * @param iterable<TournamentTrophyInterface> $rules
     */
    public function __construct(
        private TournamentEntryRepository $tournamentEntryRepository,
        private TournamentMatchRepository $tournamentMatchRepository,
        private TrophyCase $trophyCase,
        #[AutowireIterator(TournamentTrophyInterface::class)]
        private iterable $rules,
    ) {
    }

    /**
     * @return list<array{TournamentEntry, Trophy}> the trophies each player earns in the tournament, earned before or
     *                                              not, player by player in the order of the catalogue
     */
    public function trophiesEarnedIn(Tournament $tournament): array
    {
        if ($tournament->getStatus() !== TournamentStatus::Ended) {
            return [];
        }

        $rules = [...$this->rules];
        $order = array_flip(array_map(static fn (Trophy $trophy): string => $trophy->value, Trophy::cases()));
        usort($rules, static fn (TournamentTrophyInterface $a, TournamentTrophyInterface $b): int => $order[$a::getTrophy()->value] <=> $order[$b::getTrophy()->value]);

        $trophies = [];
        foreach ($this->tournamentEntryRepository->findByTournament($tournament) as $entry) {
            if ($entry->getUser()->isDeleted()) {
                continue;
            }
            foreach ($rules as $rule) {
                if ($rule->isEarnedBy($entry, $tournament)) {
                    $trophies[] = [$entry, $rule::getTrophy()];
                }
            }
        }

        return $trophies;
    }

    /**
     * Awards the trophies of the tournament (persisted, not flushed).
     *
     * @return list<array{TournamentEntry, Trophy}> the trophies earned for the first time
     */
    public function award(Tournament $tournament, \DateTimeImmutable $earnedAt): array
    {
        return array_values(array_filter(
            $this->trophiesEarnedIn($tournament),
            fn (array $earned): bool => $this->trophyCase->award($earned[0]->getUser(), $earned[1], $earnedAt, $this->lastGameOf($earned[0])),
        ));
    }

    /**
     * When the tournament ended: when its last match did (null when it has none).
     */
    public function endedAt(Tournament $tournament): ?\DateTimeImmutable
    {
        $endedAt = array_filter(array_map(
            static fn (TournamentMatch $match): ?\DateTimeImmutable => $match->getEndedAt(),
            $this->tournamentMatchRepository->findByRound($tournament, $tournament->getCurrentRound()),
        ));

        return $endedAt === [] ? null : max($endedAt);
    }

    /**
     * The game of the last match of the player, null when they won it without a game.
     */
    private function lastGameOf(TournamentEntry $entry): ?Game
    {
        $tournament = $entry->getTournament();
        for ($round = $tournament->getCurrentRound(); $round > 0; --$round) {
            foreach ($this->tournamentMatchRepository->findByRound($tournament, $round) as $match) {
                if (in_array($entry, $match->getEntries(), true)) {
                    return $match->getGame();
                }
            }
        }

        return null;
    }
}
