<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\StackType;
use App\Enum\Type;
use App\Exception\Rules\CannotFillProvinceException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PutHoldingIntoProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on any holding: the engine does not apply card effects.
 * The `put_into_province` transition fails for a holding that is not in a dynasty discard pile. The province is a
 * regular province of the owner of the holding, broken or not: no dynasty card goes on the stronghold province.
 */
#[AsMessageHandler]
final readonly class PutHoldingIntoProvinceHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        #[Target('dynasty_card')]
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(PutHoldingIntoProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        if ($card->getLogicalCard()->getType() !== Type::Holding) {
            throw new WrongCardTypeException($card, Type::Holding);
        }

        $province = $this->cardRepository->get($message->getProvinceId(), $game);
        if (! $province instanceof ProvinceCard) {
            throw new WrongCardTypeException($province, Type::Province);
        }

        if ($province->getPlayer() !== $card->getPlayer()) {
            throw new NotOwnedCardException($province, $card->getPlayer());
        }

        if ($province->getStack()->getType() !== StackType::RegularProvince) {
            throw new CannotFillProvinceException($province, 'it is the stronghold province');
        }

        $this->dynastyCardStateMachine->apply($card, 'put_into_province', ['stack' => $province->getStack()]);
    }
}
