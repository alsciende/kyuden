<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Exception\Data\DataException;
use App\Message\DrawConflictCards;
use App\Message\FillProvince;
use App\Message\GainHonor;
use App\Message\ShuffleConflictDeck;
use App\Message\ShuffleDynastyDeck;
use App\Service\PlayerStateManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

#[AsTransitionListener(workflow: 'game', transition: 'setup_game')]
readonly class SetupGameTransitionListener
{
    public const int STARTING_CARDS = 5;

    public function __construct(
        private LoggerInterface $logger,
        private MessageBusInterface $transitionBus,
        private PlayerStateManager $stateManager,
    ) {
    }

    /**
     * Setup Players
     * Determine First Player.
     */
    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->logger->debug('this is where we should setup the game', [
            'game_id' => $game->getId(),
        ]);

        foreach ($game->getPlayers() as $player) {
            $this->setup($player);
        }
    }

    /**
     * Shuffle decks.
     * Gain Starting Honor.
     * Fill Provinces.
     * Draw Starting Hand.
     */
    private function setup(Player $player): void
    {
        // shuffle decks
        $this->transitionBus->dispatch(
            new ShuffleDynastyDeck($player->getGame()->getId(), $player->getId())
        );
        $this->transitionBus->dispatch(
            new ShuffleConflictDeck($player->getGame()->getId(), $player->getId())
        );

        // gain starting honor
        $stronghold = $player->getStronghold();
        $honor = $stronghold->getLogicalCard()->getHonor();
        if ($honor === null) {
            throw new DataException('Stronghold has no honor');
        }
        $this->transitionBus->dispatch(
            new GainHonor($player->getGame()->getId(), $player->getId(), $honor)
        );

        // fill provinces
        $strongholdProvinceId = $this->stateManager->getState($player)->getStrongholdProvinceId();
        $provinces = $player->getCardsByType(Type::PROVINCE);
        $stronghold = $player->getStronghold();
        foreach ($provinces as $province) {
            if ($province->getId() === $strongholdProvinceId) {
                $province->addTopCard($stronghold);
            } else {
                $this->transitionBus->dispatch(
                    new FillProvince($player->getGame()->getId(), $player->getId(), $province->getId())
                );
            }
        }

        // draw starting hand
        $this->transitionBus->dispatch(
            new DrawConflictCards($player->getGame()->getId(), $player->getId(), self::STARTING_CARDS)
        );
    }
}
