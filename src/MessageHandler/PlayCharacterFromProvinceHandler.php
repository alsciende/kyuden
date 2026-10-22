<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Event\FatePlacedOnCardEvent;
use App\Event\FateSpentEvent;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PlayCharacterFromProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\OpportunityService;
use App\Service\ProvinceRefiller;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\CardState;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The player pays the cost of the character, which enters play ready, then places fate on it; the province is then
 * refilled (ProvinceRefiller). The player keeps the opportunity to act: they end their action (EndResolution)
 * themselves, possibly after other actions triggered by the character entering play.
 */
#[AsMessageHandler]
final readonly class PlayCharacterFromProvinceHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private CardStateManager $cardStateManager,
        private WorkflowInterface $dynastyCardStateMachine,
        private EventDispatcherInterface $eventDispatcher,
        private OpportunityService $opportunityService,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $game->assertPlace('play_cards_from_provinces');
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    public function __invoke(PlayCharacterFromProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

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
        if ($message->getCardCost() + $message->getFateCost() > 0) {
            $this->eventDispatcher->dispatch(new FateSpentEvent($game, $player, $message->getCardCost() + $message->getFateCost()));
        }
        $province = $card->getStack();
        // fails unless the card is face up in a province
        $this->dynastyCardStateMachine->apply($card, 'play');
        $this->cardStateManager->withState(
            $card,
            fn (CardState $state): CardState => $state->incrementFate($message->getFate()),
        );
        if ($message->getFate() > 0) {
            $this->eventDispatcher->dispatch(new FatePlacedOnCardEvent($game, $card, $message->getFate()));
        }
        $this->provinceRefiller->refillIfEmpty($province);

        $this->opportunityService->takeAction($player);
    }
}
