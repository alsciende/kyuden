<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Type;
use App\Exception\Data\CardNotFoundException;
use App\Exception\Data\MissingStackException;
use App\Exception\Rules\DrawingFromEmptyDeckException;
use App\Message\FillProvince;
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
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    public function __invoke(FillProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId(), 'place_provinces');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $provinces = $player->getCardsByType(Type::PROVINCE);
        foreach ($provinces as $province) {
            if ($province->getId() === $message->getProvinceId()) {
                $this->fillProvince($player, $province);

                return;
            }
        }

        throw new CardNotFoundException($message->getProvinceId());
    }

    private function fillProvince(Player $player, PhysicalCard $province): void
    {
        $dynastyDeck = $player->getCardsByPlace(DynastyCard::STATE_DRAW_DECK);
        $card = array_pop($dynastyDeck);
        if ($card === null) {
            throw new DrawingFromEmptyDeckException();
        }
        if ($province->getStack() === null) {
            throw new MissingStackException($province);
        }
        $province->getStack()->addCard($card);
        $card->setPosition(null);
        $this->dynastyCardStateMachine->apply($card, 'draw_card');
        $player->resetCardGroups();
    }
}
