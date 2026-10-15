<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\StrongholdCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\StackType;
use App\Event\ProvincePlacedEvent;
use App\Exception\Data\DataException;
use App\Factory\StackFactory;
use App\Message\DrawConflictCards;
use App\Message\FillProvince;
use App\Message\SetHonor;
use App\Message\ShuffleConflictDeck;
use App\Message\ShuffleDynastyDeck;
use App\State\Manager\PlayerStateManager;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

#[AsTransitionListener(workflow: 'game', transition: 'setup_game')]
readonly class SetupGameTransitionListener
{
    public const int STARTING_CARDS = 4;

    public function __construct(
        private MessageBusInterface $transitionBus,
        private PlayerStateManager $stateManager,
        private StackFactory $stackFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            $this->setup($player);
        }
    }

    /**
     * Shuffle decks.
     * Set Starting Honor.
     * Fill Provinces.
     * Draw Starting Hand.
     */
    private function setup(Player $player): void
    {
        // shuffle decks
        $this->transitionBus->dispatch(
            new ShuffleDynastyDeck($player->getGame()->getId(), $player->getId(), $player->getId())
        );
        $this->transitionBus->dispatch(
            new ShuffleConflictDeck($player->getGame()->getId(), $player->getId(), $player->getId())
        );

        // set starting honor
        $stronghold = $player->getStronghold();
        $honor = $stronghold->getLogicalCard()->getHonor();
        if ($honor === null) {
            throw new DataException('Stronghold has no honor');
        }
        $this->transitionBus->dispatch(
            new SetHonor($player->getGame()->getId(), $player->getId(), $player->getId(), $honor)
        );

        // fill provinces
        $strongholdProvinceStack = $player->getUniqueStack(StackType::StrongholdProvince);
        $strongholdProvinceId = $this->stateManager->getState($player)->getStrongholdProvinceId();
        foreach ($strongholdProvinceStack->getCards() as $card) {
            if ($card->getId() === $strongholdProvinceId || $card instanceof StrongholdCard) {
                continue; // skip the stronghold province and its stronghold card
            }
            $provinceStack = $this->stackFactory->createStack($player, StackType::RegularProvince);
            $provinceStack->addCard($card);
            $this->eventDispatcher->dispatch(new ProvincePlacedEvent($player->getGame(), $card, $provinceStack));
            $this->transitionBus->dispatch(
                new FillProvince($player->getGame()->getId(), $player->getId(), $card->getId())
            );
        }

        // draw starting hand
        $this->transitionBus->dispatch(
            new DrawConflictCards($player->getGame()->getId(), $player->getId(), $player->getId(), self::STARTING_CARDS)
        );
    }
}
