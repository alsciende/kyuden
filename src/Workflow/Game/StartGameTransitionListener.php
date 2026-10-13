<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\StackType;
use App\Factory\CardFactory;
use App\Factory\StackFactory;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
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
    ) {
    }

    /**
     * Fix the player order, select first player and create all cards and stacks.
     */
    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->stateManager->withState(
            $game,
            fn (GameState $state): GameState => $state
                ->setPlayerOrder(...$game->getPlayers()->toArray())
                ->setFirstPlayer($this->selectRandomFirstPlayer($game))
        );

        $this->logger->debug('StartGameTransitionListener', [
            'player_ids' => $this->stateManager->getState($game)->playerIds,
            'first_player_id' => $this->stateManager->getState($game)->firstPlayerId,
        ]);

        foreach ($game->getPlayers() as $player) {
            $this->initPlayer($player);
        }
    }

    private function selectRandomFirstPlayer(Game $game): Player
    {
        $players = $game->getPlayers()->toArray();

        return $players[array_rand($players)];
    }

    /**
     * Create all unique stacks for the player and fill them with cards.
     */
    private function initPlayer(Player $player): void
    {
        $stacks = [];
        foreach (StackType::cases() as $type) {
            if ($type->isUnique()) {
                $stacks[$type->value] = $this->stackFactory->createStack($player, $type);
            }
        }

        foreach ($player->getDeck()->getContent() as $id) {
            $logicalCard = $this->cardFactory->getLogicalCard($id);
            $this->cardFactory->createCard(
                physicalCardId: Uuid::v4()->toString(),
                logicalCard: $logicalCard,
                stack: $stacks[$logicalCard->getInitialStackType()->value]
            );
        }
    }
}
