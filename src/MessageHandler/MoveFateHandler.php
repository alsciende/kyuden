<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Element;
use App\Enum\FateHolder;
use App\Event\FateMovedEvent;
use App\Exception\Data\RingNotFoundException;
use App\Exception\Rules\NotEnoughFateException;
use App\Exception\Rules\SameFateHolderException;
use App\Message\MoveFate;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\CardState;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, between any holders: the engine does not apply card
 * effects, which decide whose fate moves where. Fate goes to a card only when it is a character in play.
 */
#[AsMessageHandler]
final readonly class MoveFateHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private CardStateManager $cardStateManager,
        private ConflictService $conflictService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(MoveFate $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $source = $this->getHolder($game, $message->getSource(), $message->getSourceId());
        $target = $this->getHolder($game, $message->getTarget(), $message->getTargetId());
        if ($source === $target) {
            throw new SameFateHolderException();
        }

        if ($target instanceof PhysicalCard) {
            $this->conflictService->assertCharacterInPlay($target);
        }

        $amount = $message->getAmount();
        $this->take($game, $source, $amount);
        $this->give($game, $target, $amount);
        $this->eventDispatcher->dispatch(new FateMovedEvent($game, $source, $target, $amount));
    }

    private function getHolder(Game $game, FateHolder $holder, string $id): Player|PhysicalCard|Element
    {
        return match ($holder) {
            FateHolder::Player => $this->playerRepository->get($id, $game),
            FateHolder::Card => $this->cardRepository->get($id, $game),
            FateHolder::Ring => Element::tryFrom($id) ?? throw new RingNotFoundException($id),
        };
    }

    /**
     * @throws NotEnoughFateException if the holder holds less fate
     */
    private function take(Game $game, Player|PhysicalCard|Element $holder, int $amount): void
    {
        if ($holder instanceof Player) {
            $this->playerStateManager->withState($holder, fn (PlayerState $state): PlayerState => $state->spendFate($amount));
        } elseif ($holder instanceof PhysicalCard) {
            $this->cardStateManager->withState($holder, fn (CardState $state): CardState => $state->removeFate($amount));
        } else {
            $this->gameStateManager->withState($game, function (GameState $state) use ($holder, $amount): GameState {
                $ring = $state->getRing($holder);
                if ($amount > $ring->fate) {
                    throw new NotEnoughFateException($amount, $ring->fate);
                }

                $ring->fate -= $amount;

                return $state;
            });
        }
    }

    private function give(Game $game, Player|PhysicalCard|Element $holder, int $amount): void
    {
        if ($holder instanceof Player) {
            $this->playerStateManager->withState($holder, fn (PlayerState $state): PlayerState => $state->incrementFate($amount));
        } elseif ($holder instanceof PhysicalCard) {
            $this->cardStateManager->withState($holder, fn (CardState $state): CardState => $state->incrementFate($amount));
        } else {
            $this->gameStateManager->withState($game, function (GameState $state) use ($holder, $amount): GameState {
                $state->getRing($holder)->fate += $amount;

                return $state;
            });
        }
    }
}
