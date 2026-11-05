<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Side;
use App\Enum\Type;
use App\Exception\Rules\CannotFillProvinceException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardSideException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PutCardIntoProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ProvinceRefiller;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on any dynasty card: the engine does not apply card
 * effects. The card goes on top of the province, face up or face down (transitions `move_to_province_faceup` and
 * `move_to_province_facedown`, from the deck, the discard pile, a province or play: a character leaving play follows
 * the rules of any card leaving play). The province is a province of the owner of the card, the stronghold province
 * included (Recalled Defenses), broken or not. A province the card has left is refilled once the effect is resolved.
 */
#[AsMessageHandler]
final readonly class PutCardIntoProvinceHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private ProvinceRefiller $provinceRefiller,
        #[Target('dynasty_card')]
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(PutCardIntoProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        if (! $card instanceof DynastyCard) {
            throw new WrongCardSideException($card, Side::Dynasty);
        }

        $province = $this->cardRepository->get($message->getProvinceId(), $game);
        if (! $province instanceof ProvinceCard) {
            throw new WrongCardTypeException($province, Type::Province);
        }

        if ($province->getPlayer() !== $card->getPlayer()) {
            throw new NotOwnedCardException($province, $card->getPlayer());
        }

        $from = $card->getStack();
        $to = $province->getStack();
        if ($from === $to) {
            throw new CannotFillProvinceException($province, sprintf('card [%s] is already in it', $card->getId()));
        }

        $this->dynastyCardStateMachine->apply($card, $message->isFaceUp() ? 'move_to_province_faceup' : 'move_to_province_facedown', ['stack' => $to]);

        // a province the card has left: nothing happens for another stack
        $this->provinceRefiller->schedule($from);
    }
}
