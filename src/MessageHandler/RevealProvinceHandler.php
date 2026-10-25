<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Side;
use App\Exception\Rules\NoFacedownProvinceException;
use App\Exception\Rules\WrongCardSideException;
use App\Message\RevealProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Turns a face-down province face up, whoever owns it: the step after a conflict declaration waits for the attacked
 * province to be revealed (reveal_attacked_province).
 */
#[AsMessageHandler]
final readonly class RevealProvinceHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        #[Target('province_card')]
        private WorkflowInterface $provinceCardStateMachine,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game, while a province is face down
        // through the stacks, which the view of the game loads with it
        foreach ($game->getPlayers() as $owner) {
            foreach ($owner->getStacks() as $stack) {
                foreach ($stack->getCards() as $card) {
                    if ($card instanceof ProvinceCard && $card->getCurrentPlace() === ProvinceCard::STATE_FACEDOWN) {
                        return;
                    }
                }
            }
        }

        throw new NoFacedownProvinceException();
    }

    public function __invoke(RevealProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        // the workflow of a province does not know the places of the other cards
        if (! $card instanceof ProvinceCard) {
            throw new WrongCardSideException($card, Side::Province);
        }
        $this->provinceCardStateMachine->apply($card, 'reveal');
    }
}
