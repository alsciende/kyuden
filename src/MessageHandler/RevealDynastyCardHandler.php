<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Side;
use App\Exception\Rules\WrongCardSideException;
use App\Message\RevealDynastyCard;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
final readonly class RevealDynastyCardHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(RevealDynastyCard $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        // the workflow of a dynasty card does not know the places of the other cards
        if (! $card instanceof DynastyCard) {
            throw new WrongCardSideException($card, Side::Dynasty);
        }
        $this->dynastyCardStateMachine->apply(
            $card,
            'turn_faceup'
        );
    }
}
