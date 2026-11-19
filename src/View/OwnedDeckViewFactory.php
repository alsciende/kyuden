<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\Clan;
use App\Enum\VictoryCondition;
use App\Repository\GameRepository;
use App\State\Manager\GameStateManager;
use App\View\Model\DeckEndingRecordView;
use App\View\Model\DeckLastGameView;
use App\View\Model\DeckOpponentRecordView;
use App\View\Model\DeckRecordView;
use App\View\Model\DeckView;
use App\View\Model\OwnedDeckView;

/**
 * Builds the views of the decks of a user with their record (HTTP API), from the games they have played (not the
 * replays) in three queries whatever their number: the strongholds of the decks, the games with their players, the
 * clans of the strongholds played.
 */
final readonly class OwnedDeckViewFactory
{
    public function __construct(
        private DeckViewFactory $deckViewFactory,
        private GameRepository $gameRepository,
        private GameStateManager $gameStateManager,
    ) {
    }

    /**
     * @param list<Deck> $decks decks of the user
     *
     * @return list<OwnedDeckView> the last played first (by the end of their last game), then those never played, in
     *                             the order given
     */
    public function createAll(User $user, array $decks): array
    {
        $games = $this->gameRepository->findByUser($user->getId());
        $clans = $this->gameRepository->findStrongholdClans(array_map(fn (Game $game): string => $game->getId(), $games));

        $views = array_map(fn (Deck $deck, DeckView $view): OwnedDeckView => new OwnedDeckView(
            $view,
            $this->createRecord($deck, $user, $games, $clans),
        ), $decks, $this->deckViewFactory->createAll($decks));
        $lastPlayedAt = static fn (OwnedDeckView $view): int => $view->record->lastGame?->endedAt?->getTimestamp() ?? \PHP_INT_MIN;
        usort($views, static fn (OwnedDeckView $a, OwnedDeckView $b): int => $lastPlayedAt($b) <=> $lastPlayedAt($a));

        return $views;
    }

    public function create(User $user, Deck $deck): OwnedDeckView
    {
        return $this->createAll($user, [$deck])[0];
    }

    /**
     * @param list<Game>           $games the games of the user, with their players
     * @param array<string, Clan>  $clans the clan of the stronghold of each player, by id of the player
     */
    private function createRecord(Deck $deck, User $user, array $games, array $clans): DeckRecordView
    {
        $played = $won = $lost = $inProgress = $rankedWon = $rankedLost = 0;
        /** @var array<string, array{won: int, lost: int}> $endings */
        $endings = [];
        /** @var array<string, array{won: int, lost: int}> $opponents */
        $opponents = [];
        $lastGame = null;

        foreach ($games as $game) {
            $players = $game->getPlayers()->getValues();
            $mine = array_find($players, fn (Player $player): bool => $player->getUserId() === $user->getId());
            if (! $mine instanceof Player || $mine->getDeck()->getId() !== $deck->getId()) {
                continue;
            }
            if ($game->getCurrentPlace() !== Game::STATE_ENDED) {
                $inProgress += $game->getCurrentPlace() === 'join_game' ? 0 : 1;

                continue;
            }

            ++$played;
            $state = $this->gameStateManager->getState($game);
            $opponent = array_find($players, fn (Player $player): bool => $player !== $mine);
            $opponentClan = $opponent instanceof Player ? ($clans[$opponent->getId()] ?? null) : null;
            $result = $state->winnerId === null ? null : $state->winnerId === $mine->getId();
            if ($result !== null) {
                $key = $result ? 'won' : 'lost';
                $result ? ++$won : ++$lost;
                if ($game->isStandard()) {
                    $result ? ++$rankedWon : ++$rankedLost;
                }
                if ($state->victoryCondition instanceof VictoryCondition) {
                    $endings[$state->victoryCondition->value] ??= ['won' => 0, 'lost' => 0];
                    ++$endings[$state->victoryCondition->value][$key];
                }
                if ($opponentClan instanceof Clan) {
                    $opponents[$opponentClan->value] ??= ['won' => 0, 'lost' => 0];
                    ++$opponents[$opponentClan->value][$key];
                }
            }

            $endedAt = $game->getUpdatedAt();
            if (! $lastGame instanceof DeckLastGameView || ($endedAt?->getTimestamp() ?? 0) > ($lastGame->endedAt?->getTimestamp() ?? 0)) {
                $lastGame = new DeckLastGameView($game->getId(), $endedAt, $result, $opponentClan);
            }
        }

        $endingViews = [];
        foreach (VictoryCondition::cases() as $condition) {
            if (isset($endings[$condition->value])) {
                $endingViews[] = new DeckEndingRecordView($condition, $endings[$condition->value]['won'], $endings[$condition->value]['lost']);
            }
        }
        $opponentViews = [];
        foreach (Clan::cases() as $clan) {
            if (isset($opponents[$clan->value])) {
                $opponentViews[] = new DeckOpponentRecordView($clan, $opponents[$clan->value]['won'], $opponents[$clan->value]['lost']);
            }
        }

        return new DeckRecordView($played, $won, $lost, $inProgress, $rankedWon, $rankedLost, $lastGame, $endingViews, $opponentViews);
    }
}
