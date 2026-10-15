<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Event\StrongholdProvinceChosenEvent;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\ChooseStrongholdProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChooseStrongholdProvinceHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $game->assertPlace('choose_stronghold');
    }

    public function __invoke(ChooseStrongholdProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $logicalCard = $card->getLogicalCard();

        if ($logicalCard->getType() !== Type::Province) {
            throw new WrongCardTypeException($card, Type::Province);
        }

        $this->stateManager->withState(
            $player,
            fn (PlayerState $state) => $state->setStrongholdProvinceId($card->getId()),
        );
        $this->eventDispatcher->dispatch(new StrongholdProvinceChosenEvent($game, $player, $card));
    }
}
