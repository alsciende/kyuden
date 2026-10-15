<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\ProvinceStrengthDeclaredEvent;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\NotAttackedProvinceException;
use App\Exception\Rules\NotDefendingPlayerException;
use App\Message\DeclareProvinceStrength;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Replaces the printed strength of an attacked province (DetermineResultEnterListener) with the one declared by the
 * defending player.
 */
#[AsMessageHandler]
final readonly class DeclareProvinceStrengthHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $game->assertPlace('determine_result');
        $conflict = $this->gameStateManager->getState($game)->conflict ?? throw new NoConflictException();
        if ($player->getId() !== $conflict->defendingPlayerId) {
            throw new NotDefendingPlayerException();
        }
    }

    public function __invoke(DeclareProvinceStrength $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->gameStateManager->withState($game, function (GameState $state) use ($message): GameState {
            $conflict = $state->currentConflict();
            if (! in_array($message->getProvinceId(), $conflict->attackedProvinceIds, true)) {
                throw new NotAttackedProvinceException();
            }

            $conflict->provinceStrengths[$message->getProvinceId()] = $message->getStrength();

            return $state;
        });
        $this->eventDispatcher->dispatch(new ProvinceStrengthDeclaredEvent($game, $player, $this->cardRepository->get($message->getProvinceId(), $game), $message->getStrength()));
    }
}
