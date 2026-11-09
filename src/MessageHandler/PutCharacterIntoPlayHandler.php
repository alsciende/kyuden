<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Event\CharacterPutIntoConflictEvent;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\NotInConflictException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PutCharacterIntoPlay;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ProvinceRefiller;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on any character: the engine does not apply card
 * effects, which decide what is put into play. The `put_into_play` transition fails for a character that is neither in
 * a hand, face up in a province, in a deck, in a discard pile nor set aside. Under the control of another player than its owner, it enters
 * play then changes control (`take_control`: both are logged). Into the conflict, the character joins the side of its
 * controller, who must be the attacking or the defending player.
 */
#[AsMessageHandler]
final readonly class PutCharacterIntoPlayHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        #[Target('conflict_card')]
        private WorkflowInterface $conflictCardStateMachine,
        #[Target('dynasty_card')]
        private WorkflowInterface $dynastyCardStateMachine,
        private ProvinceRefiller $provinceRefiller,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(PutCharacterIntoPlay $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        if ($card->getLogicalCard()->getType() !== Type::Character) {
            throw new WrongCardTypeException($card, Type::Character);
        }

        $controller = $message->getControllerId() === null ? $card->getPlayer() : $this->playerRepository->get($message->getControllerId(), $game);
        if ($message->isIntoConflict()) {
            $conflict = $this->gameStateManager->getState($game)->conflict ?? throw new NoConflictException();
            if (! in_array($controller->getId(), [$conflict->attackingPlayerId, $conflict->defendingPlayerId], true)) {
                throw new NotInConflictException();
            }
        }

        $from = $card->getStack();
        $workflow = $card instanceof ConflictCard ? $this->conflictCardStateMachine : $this->dynastyCardStateMachine;
        $workflow->apply($card, 'put_into_play');
        if ($controller !== $card->getPlayer()) {
            $workflow->apply($card, 'take_control', [
                'controller' => $controller,
            ]);
        }

        if ($message->isIntoConflict()) {
            $this->gameStateManager->withState($game, function (GameState $state) use ($controller, $card): GameState {
                $conflict = $state->currentConflict();
                if ($controller->getId() === $conflict->attackingPlayerId) {
                    $conflict->attackerIds[] = $card->getId();
                } else {
                    $conflict->defenderIds[] = $card->getId();
                }

                return $state;
            });
            $this->eventDispatcher->dispatch(new CharacterPutIntoConflictEvent($game, $card));
        }

        $this->provinceRefiller->schedule($from);
    }
}
