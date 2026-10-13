<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\Message\FillProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\DrawDeckService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Puts the top card of the dynasty deck face down on the province: during setup, and whenever a card has left the
 * province (played, discarded from a broken province or during the fate phase). Accepted in any game place; the player
 * does not need to be the active player.
 * An empty deck is replaced by the shuffled discard pile (DrawDeckService); when the discard pile is empty too, the
 * province stays empty.
 */
#[AsMessageHandler]
final readonly class FillProvinceHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private WorkflowInterface $dynastyCardStateMachine,
        private DrawDeckService $drawDeckService,
    ) {
    }

    public function __invoke(FillProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $card = $this->cardRepository->get($message->getProvinceId(), $game);

        $this->fillProvince($player, $card);
    }

    private function fillProvince(Player $player, PhysicalCard $province): void
    {
        $card = $this->drawDeckService->getTopCard($player, StackType::DynastyDrawDeck);
        if (! $card instanceof DynastyCard) {
            return;
        }

        $this->dynastyCardStateMachine->apply($card, 'draw_card', [
            'stack' => $province->getStack(),
        ]);
    }
}
