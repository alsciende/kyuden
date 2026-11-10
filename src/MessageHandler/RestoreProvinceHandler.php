<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Exception\Rules\NoBrokenProvinceException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\RestoreProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on any broken province (transition `restore`): the
 * engine does not apply card effects. The province is face up and unbroken again, with what lies on it; a game whose
 * stronghold province was broken has ended (GameEndedMiddleware).
 */
#[AsMessageHandler]
final readonly class RestoreProvinceHandler implements PlayerActionHandlerInterface
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
        // accepted in any game place, from any player of the game, while a province is broken
        // through the stacks, which the view of the game loads with it
        foreach ($game->getPlayers() as $owner) {
            foreach ($owner->getStacks() as $stack) {
                foreach ($stack->getCards() as $card) {
                    if ($card instanceof ProvinceCard && $card->getCurrentPlace() === ProvinceCard::STATE_BROKEN) {
                        return;
                    }
                }
            }
        }

        throw new NoBrokenProvinceException();
    }

    public function __invoke(RestoreProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $province = $this->cardRepository->get($message->getProvinceId(), $game);
        if (! $province instanceof ProvinceCard) {
            throw new WrongCardTypeException($province, Type::Province);
        }

        $this->provinceCardStateMachine->apply($province, 'restore');
    }
}
