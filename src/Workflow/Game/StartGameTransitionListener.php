<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Side;
use App\Enum\StackType;
use App\Event\FirstPlayerChangedEvent;
use App\Factory\CardFactory;
use App\Factory\StackFactory;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

#[AsTransitionListener(workflow: 'game', transition: 'start_game')]
readonly class StartGameTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private CardFactory $cardFactory,
        private StackFactory $stackFactory,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Fix the player order, select first player, set the rings and create all cards and stacks.
     *
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();

        $firstPlayer = $this->selectRandomFirstPlayer($game);
        $this->stateManager->withState(
            $game,
            fn (GameState $state): GameState => $state
                ->setPlayerOrder(...$game->getPlayers()->toArray())
                ->setFirstPlayer($firstPlayer)
                ->initRings()
        );

        $this->logger->debug('StartGameTransitionListener', [
            'player_ids' => $this->stateManager->getState($game)->playerIds,
            'first_player_id' => $this->stateManager->getState($game)->firstPlayerId,
        ]);

        $this->eventDispatcher->dispatch(new FirstPlayerChangedEvent($game, $firstPlayer));
        foreach ($game->getPlayers() as $player) {
            $this->initPlayer($player);
        }
    }

    private function selectRandomFirstPlayer(Game $game): Player
    {
        $players = $game->getPlayers()->getValues();

        return $players[$game->getRandomizer()->getInt(0, count($players) - 1)];
    }

    /**
     * Create all unique stacks for the player and fill them with cards. The stronghold is created first, at the bottom
     * of the stronghold province stack, under the provinces; the ids are drawn in the order of the deck all the same,
     * so that the games created before replay with the same ids.
     */
    private function initPlayer(Player $player): void
    {
        $stacks = [];
        foreach (StackType::cases() as $type) {
            if ($type->isUnique()) {
                $stacks[$type->value] = $this->stackFactory->createStack($player, $type);
            }
        }

        $cards = [];
        foreach ($player->getDeck()->getCardCodes() as $code) {
            $cards[] = [$player->getGame()->generateId(), $this->cardFactory->getLogicalCard($code)];
        }
        usort($cards, static fn (array $a, array $b): int => ($b[1]->getSide() === Side::Stronghold) <=> ($a[1]->getSide() === Side::Stronghold));

        foreach ($cards as [$id, $logicalCard]) {
            $stackType = $logicalCard->getInitialStackType();
            $this->cardFactory->createCard(
                physicalCardId: $id,
                logicalCard: $logicalCard,
                stack: $stacks[$stackType->value] ?? throw new \LogicException(sprintf('The initial stack %s of card %s is not unique.', $stackType->name, $logicalCard->getId())),
            );
        }
    }
}
