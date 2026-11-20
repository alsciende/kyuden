<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Event\ProvinceBrokenEvent;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\BreakProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ControlService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on any province: the engine does not apply card
 * effects. The `break` transition fails for a province that is face down or already broken. A province broken this way
 * during a conflict is not broken again when the result is applied; a broken stronghold province ends the game
 * (VictoryService).
 */
#[AsMessageHandler]
final readonly class BreakProvinceHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        #[Target('province_card')]
        private WorkflowInterface $provinceCardStateMachine,
        private ControlService $controlService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(BreakProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $player);
        $province = $this->cardRepository->get($message->getProvinceId(), $game);
        if (! $province instanceof ProvinceCard) {
            throw new WrongCardTypeException($province, Type::Province);
        }

        $this->provinceCardStateMachine->apply($province, 'break');
        // the sender breaks it, by the effect they apply
        $this->eventDispatcher->dispatch(new ProvinceBrokenEvent($game, $province, $this->controlService->getController($province), $player));
    }
}
