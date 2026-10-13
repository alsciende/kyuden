<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInActionWindowException;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\TriggerActionAbility;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Allowed in any game place marked `action_window` in the game workflow. The card must be where its abilities can be
 * used (`trigger_action` transition of its workflow). Neither the presence of an action ability on the card nor its
 * limit per round is checked, since card effects can change them.
 * The player keeps the opportunity to act: they pay the costs and resolve the effects with other actions, then end
 * their opportunity (EndOpportunity).
 */
#[AsMessageHandler]
final readonly class TriggerActionAbilityHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private WorkflowInterface $gameStateMachine,
        private Registry $workflowRegistry,
    ) {
    }

    public function __invoke(TriggerActionAbility $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $place = $game->getCurrentPlace();
        if ($place === null || ($this->gameStateMachine->getMetadataStore()->getPlaceMetadata($place)['action_window'] ?? false) !== true) {
            throw new NotInActionWindowException($place);
        }

        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }

        // the controller of the card should be checked, but control is not modeled yet
        $card = $this->cardRepository->get($message->getCardId(), $game);
        if ($card->getPlayer() !== $player) {
            throw new NotOwnedCardException($card, $player);
        }

        // fails unless the card is where its abilities can be used
        $this->workflowRegistry->get($card)->apply($card, 'trigger_action');
    }
}
