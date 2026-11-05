<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\StackType;
use App\Enum\Type;
use App\Exception\Rules\CannotFillProvinceException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\FillProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ProvinceRefiller;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Puts the top card of the dynasty deck on the province, face down or face up (ProvinceRefiller): during setup, and for
 * the effects that fill a province or add a card to it (Rally, City of the Rich Frog). A province a dynasty card has
 * left is refilled by the engine. Accepted in any game place, from any player of the game, on any regular province,
 * whatever the cards on it: the stronghold province is not filled.
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
        if (! $province instanceof ProvinceCard) {
            throw new WrongCardTypeException($province, Type::Province);
        }

        if ($province->getStack()->getType() !== StackType::RegularProvince) {
            throw new CannotFillProvinceException($province, 'it is the stronghold province');
        }

        // the province is filled from the deck of its owner
        $this->provinceRefiller->fill($province, $message->isFaceUp());
    }
}
