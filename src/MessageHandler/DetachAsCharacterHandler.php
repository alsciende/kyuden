<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Rules\NotAttachedException;
use App\Message\DetachAsCharacter;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Accepted in any game place and from any player of the game, on a card attached to a character in play (an attachment,
 * or a character played as one; not an attachment on a province): the engine does not apply card effects. The `detach`
 * transition puts it into a new stack of its controller, ready, at home: it keeps its controller and its state, and the
 * cards under it stay there.
 */
#[AsMessageHandler]
final readonly class DetachAsCharacterHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private Registry $workflowRegistry,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(DetachAsCharacter $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        if ($card->getAttachedCharacter() === null) {
            throw new NotAttachedException($card);
        }

        $this->workflowRegistry->get($card)->apply($card, 'detach');
    }
}
