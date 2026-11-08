<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Side;
use App\Exception\Rules\NoFaceupProvinceException;
use App\Exception\Rules\WrongCardSideException;
use App\Message\TurnProvinceFacedown;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on any unbroken face-up province (transition
 * `unreveal`): the engine does not apply card effects, nor the Eminent keyword. The province keeps its id: the players
 * have seen it, and it stays where it is.
 */
#[AsMessageHandler]
final readonly class TurnProvinceFacedownHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        #[Target('province_card')]
        private WorkflowInterface $provinceCardStateMachine,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game, while a province is unbroken and face up
        // through the stacks, which the view of the game loads with it
        foreach ($game->getPlayers() as $owner) {
            foreach ($owner->getStacks() as $stack) {
                foreach ($stack->getCards() as $card) {
                    if ($card instanceof ProvinceCard && $card->getCurrentPlace() === ProvinceCard::STATE_UNBROKEN) {
                        return;
                    }
                }
            }
        }

        throw new NoFaceupProvinceException();
    }

    public function __invoke(TurnProvinceFacedown $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        // the workflow of a province does not know the places of the other cards
        if (! $card instanceof ProvinceCard) {
            throw new WrongCardSideException($card, Side::Province);
        }
        $this->provinceCardStateMachine->apply($card, 'unreveal');
    }
}
