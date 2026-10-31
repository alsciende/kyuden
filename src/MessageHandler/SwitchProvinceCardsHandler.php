<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\StackType;
use App\Exception\Rules\NotInProvinceException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\SameProvinceException;
use App\Message\SwitchProvinceCards;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on any dynasty cards: the engine does not apply card
 * effects. Each card goes on top of the province of the other one, face up or face down as it was (transitions
 * `switch_province_faceup` and `switch_province_facedown`). No province is left empty: nothing is refilled.
 */
#[AsMessageHandler]
final readonly class SwitchProvinceCardsHandler implements PlayerActionHandlerInterface
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

    public function __invoke(SwitchProvinceCards $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->assertInProvince($this->cardRepository->get($message->getCardId(), $game));
        $other = $this->assertInProvince($this->cardRepository->get($message->getOtherCardId(), $game));
        if ($other->getPlayer() !== $card->getPlayer()) {
            throw new NotOwnedCardException($other, $card->getPlayer());
        }

        $province = $card->getStack();
        $otherProvince = $other->getStack();
        if ($province === $otherProvince) {
            throw new SameProvinceException($card, $other);
        }

        $this->move($card, $otherProvince);
        $this->move($other, $province);
    }

    private function assertInProvince(PhysicalCard $card): DynastyCard
    {
        if (! $card instanceof DynastyCard || $card->getStack()->getType() !== StackType::RegularProvince) {
            throw new NotInProvinceException($card);
        }

        return $card;
    }

    private function move(DynastyCard $card, Stack $province): void
    {
        $transition = $card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEUP ? 'switch_province_faceup' : 'switch_province_facedown';
        $this->dynastyCardStateMachine->apply($card, $transition, ['stack' => $province]);
    }
}
