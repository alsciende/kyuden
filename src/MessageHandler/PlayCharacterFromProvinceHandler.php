<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\Type;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PlayCharacterFromProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\CardState;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The player pays the cost of the character, which enters play ready, then places fate on it.
 * The player keeps the opportunity to act: they refill the province (FillProvince) and end their opportunity
 * (EndOpportunity) themselves, possibly after other actions triggered by the character entering play.
 */
#[AsMessageHandler]
final readonly class PlayCharacterFromProvinceHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private CardStateManager $cardStateManager,
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    public function __invoke(PlayCharacterFromProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId(), 'play_cards_from_provinces');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }

        $card = $this->cardRepository->get($message->getCardId(), $game);
        if ($card->getPlayer() !== $player) {
            throw new NotOwnedCardException($card, $player);
        }

        if ($card->getLogicalCard()->getType() !== Type::Character) {
            throw new WrongCardTypeException($card, Type::Character);
        }

        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->spendFate($message->getCardCost() + $message->getFateCost()),
        );
        // fails unless the card is face up in a province
        $this->dynastyCardStateMachine->apply($card, 'play');
        $this->cardStateManager->withState(
            $card,
            fn (CardState $state): CardState => $state->incrementFate($message->getFate()),
        );
    }
}
