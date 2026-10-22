<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\FillProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ProvinceRefiller;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Puts the top card of the dynasty deck face down on the province (ProvinceRefiller): during setup, and for the effects
 * that fill a province. A province a dynasty card has left is refilled by the engine. Accepted in any game place; the
 * player does not need to be the active player.
 */
#[AsMessageHandler]
final readonly class FillProvinceHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(FillProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $province = $this->cardRepository->get($message->getProvinceId(), $game);

        // the province is filled from the deck of its owner
        $this->provinceRefiller->fill($province);
    }
}
