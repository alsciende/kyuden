<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\Enum\Type;
use App\Factory\CardFactory;
use App\Service\StackManager;
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
        private StackManager $stackManager,
        private CardFactory $factory,
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

    private function initPlayer(Player $player): void
    {
        $hand = $this->stackManager->new($player, StackType::Hand);

        foreach ($player->getDeck()->getContent() as $id) {
            $card = $this->factory->createCard(Uuid::v4()->toString(), $id);
            $player->addCard($card);
            $player->getGame()->addCard($card);
            // all cards start in hand and will be moved to the correct stacks later
            $card->setStack($hand);
        }

        $this->stackManager->new($player, StackType::ConflictDrawDeck)
            ->addPlayerCards($player, fn (PhysicalCard $card): bool => $card->getCurrentPlace() === ConflictCard::STATE_DRAW_DECK);

        $this->stackManager->new($player, StackType::ConflictDiscardPile);

        $this->stackManager->new($player, StackType::DynastyDrawDeck)
            ->addPlayerCards($player, fn (PhysicalCard $card): bool => $card->getCurrentPlace() === DynastyCard::STATE_DRAW_DECK);

        $this->stackManager->new($player, StackType::DynastyDiscardPile);

        $this->stackManager->new($player, StackType::Role);

        // 5 provinces
        $provinceCards = $player->getPhysicalCards()->filter(
            fn (PhysicalCard $card): bool => $card->getLogicalCard()->getType() === Type::Province
        );
        foreach ($provinceCards as $provinceCard) {
            $this->stackManager->new($player, StackType::Province)
                ->addCard($provinceCard);
        }
    }
}
