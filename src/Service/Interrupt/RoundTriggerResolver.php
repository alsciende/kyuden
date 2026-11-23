<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\Game;
use App\Enum\Phase;
use App\Exception\Data\DataException;
use App\Exception\Rules\RulesException;
use App\Message\ActionInterface;
use App\Message\DeclareDuelStatistic;
use App\Message\DrawConflictCards;
use App\Service\AvailableActionsResolver;
use App\State\Manager\GameStateManager;
use App\State\Model\DuelState;
use App\Trigger\CardsDrawingTrigger;
use App\Trigger\DuelResolvingTrigger;
use App\Trigger\FirstPlayerTokenPassingTrigger;

/**
 * The other conditions cards interrupt: the first player token passed (`first_player_token_passed`), a duel resolved
 * once its last statistic is declared (DeclareDuelStatistic), and conflict cards drawn by an effect outside of the draw
 * phase (DrawConflictCards).
 */
final readonly class RoundTriggerResolver implements ImminentTriggerResolverInterface
{
    public function __construct(
        private AvailableActionsResolver $availableActionsResolver,
        private GameStateManager $gameStateManager,
    ) {
    }

    #[\Override]
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        try {
            return match (true) {
                $message instanceof DeclareDuelStatistic => $this->duelResolving($message, $game),
                $message instanceof DrawConflictCards => $this->cardsDrawing($message, $game),
                default => [],
            };
        } catch (DataException|RulesException) {
            // refused by its handler: nothing would occur
            return [];
        }
    }

    #[\Override]
    public function resolveTransition(string $transition, Game $game): array
    {
        if ($transition !== 'first_player_token_passed') {
            return [];
        }

        $state = $this->gameStateManager->getState($game);
        $firstPlayer = $game->getPlayer($state->firstPlayerId ?? throw new \LogicException('The game has no first player.'));

        return [new FirstPlayerTokenPassingTrigger($firstPlayer, $game->getPlayer($state->getNextPlayerId($firstPlayer)))];
    }

    /**
     * The last statistic of the duel: it would resolve.
     *
     * @return list<DuelResolvingTrigger>
     */
    private function duelResolving(DeclareDuelStatistic $message, Game $game): array
    {
        $player = $game->getPlayer($message->getPlayerId());
        $this->availableActionsResolver->getHandler(DeclareDuelStatistic::class)->assertAvailable($game, $player);
        $duel = $this->gameStateManager->getState($game)->duel;
        \assert($duel instanceof DuelState);
        $otherId = $player->getId() === $duel->challengerId ? $duel->challengedId : $duel->challengerId;
        if (! $duel->statisticDeclaredBy($otherId)) {
            return [];
        }

        return [new DuelResolvingTrigger(
            $game->getPlayer($duel->challengerId),
            $game->getPlayer($duel->challengedId),
            [...$duel->challengerCardIds, ...$duel->challengedCardIds],
        )];
    }

    /**
     * @return list<CardsDrawingTrigger>
     */
    private function cardsDrawing(DrawConflictCards $message, Game $game): array
    {
        $game->getPlayer($message->getPlayerId());
        if ($message->getQuantity() <= 0 || Phase::ofPlace($game->getCurrentPlace()) === Phase::Draw) {
            return [];
        }

        return [new CardsDrawingTrigger($game->getPlayer($message->getTargetPlayerId()), $message->getQuantity())];
    }
}
