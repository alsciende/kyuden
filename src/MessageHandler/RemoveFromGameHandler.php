<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\RemoveFromGame;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ProvinceRefiller;
use App\Service\SetAsideStacks;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Accepted in any game place and from any player of the game, on any card: the engine does not apply card effects,
 * which decide what is removed from the game. The `remove_from_game` transitions of the conflict and dynasty card
 * workflows fail for the other cards (provinces, strongholds, roles) and for a card already removed. Face up, the card
 * goes to the stack of its owner; face down, to the stack of the sender, the only player who sees its face there
 * (Visibility). A province a dynasty card leaves is refilled once the effect is resolved, if it is still empty.
 */
#[AsMessageHandler]
final readonly class RemoveFromGameHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private Registry $workflowRegistry,
        private SetAsideStacks $setAsideStacks,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(RemoveFromGame $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $player);
        $card = $this->cardRepository->get($message->getCardId(), $game);

        $from = $card->getStack();
        $this->workflowRegistry->get($card)->apply($card, $message->isFaceDown() ? 'remove_from_game_facedown' : 'remove_from_game', [
            // a stack created by a transition refused is rolled back with the rest
            'stack' => $this->setAsideStacks->removedFromGame($message->isFaceDown() ? $player : $card->getPlayer()),
        ]);
        if ($card instanceof DynastyCard) {
            $this->provinceRefiller->schedule($from);
        }
    }
}
