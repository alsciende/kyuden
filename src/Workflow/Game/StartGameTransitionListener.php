<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\CardTypes\RoleCard;
use App\Entity\CardTypes\StrongholdCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
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
     * Select first player and create all cards and stacks.
     */
    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->stateManager->withState(
            $game,
            fn (GameState $state): GameState => $state->setFirstPlayer($this->selectRandomFirstPlayer($game))
        );

        $this->logger->debug('StartGameTransitionListener', [
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
        $hand = $this->stackFactory->createStack($player, StackType::Hand);

        foreach ($player->getDeck()->getContent() as $id) {
            $card = $this->cardFactory->createCard(Uuid::v4()->toString(), $id, $hand);
            $player->addCard($card);
            $player->getGame()->addCard($card);
            // all cards start in hand and will be moved to the correct stacks later
            $card->setStack($hand);
        }

        $this->stackFactory->createStack($player, StackType::ConflictDrawDeck)
            ->addPlayerCards($player, fn (PhysicalCard $card): bool => $card instanceof ConflictCard);

        $this->stackFactory->createStack($player, StackType::ConflictDiscardPile);

        $this->stackFactory->createStack($player, StackType::DynastyDrawDeck)
            ->addPlayerCards($player, fn (PhysicalCard $card): bool => $card instanceof DynastyCard);

        $this->stackFactory->createStack($player, StackType::DynastyDiscardPile);

        $this->stackFactory->createStack($player, StackType::Role)
            ->addPlayerCards($player, fn (PhysicalCard $card): bool => $card instanceof RoleCard);

        // we put all the province cards in the first province stack, as well as the stronghold card,
        // they will be moved to the correct stacks later
        $this->stackFactory->createStack($player, StackType::StrongholdProvince)
            ->addPlayerCards($player, fn (PhysicalCard $card): bool => $card instanceof ProvinceCard)
            ->addPlayerCards($player, fn (PhysicalCard $card): bool => $card instanceof StrongholdCard);
    }
}
