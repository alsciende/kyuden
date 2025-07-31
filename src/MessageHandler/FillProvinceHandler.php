<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\Exception\Rules\DrawingFromEmptyDeckException;
use App\Message\FillProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
final readonly class FillProvinceHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    public function __invoke(FillProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId(), 'place_provinces');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $card = $this->cardRepository->get($message->getProvinceId(), $game);

        $this->fillProvince($player, $card);
    }

    private function fillProvince(Player $player, PhysicalCard $province): void
    {
        $stack = $player->getUniqueStack(StackType::DynastyDrawDeck);
        $card = $stack->getCards()->first();
        if (! $card instanceof DynastyCard) {
            throw new DrawingFromEmptyDeckException();
        }

        $this->dynastyCardStateMachine->apply($card, 'draw_card', [
            'province' => $province,
        ]);
    }
}
