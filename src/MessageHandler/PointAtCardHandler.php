<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\CardPointedAtEvent;
use App\Exception\Rules\HiddenCardException;
use App\Message\PointAtCard;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\View\Visibility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game. The card must be in a stack every player sees, so that
 * the opponent knows which card is pointed at; the log shows its face to those who see it.
 */
#[AsMessageHandler]
final readonly class PointAtCardHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private Visibility $visibility,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(PointAtCard $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $stack = $card->getStack();
        foreach ($game->getPlayers() as $viewer) {
            if (! $this->visibility->isStackVisibleTo($stack->getType(), $stack->getPlayer(), $viewer)) {
                throw new HiddenCardException($card);
            }
        }

        $this->eventDispatcher->dispatch(new CardPointedAtEvent($game, $player, $card, $stack));
    }
}
