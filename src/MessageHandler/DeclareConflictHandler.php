<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Rules\NoAttackerException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\RingNotUnclaimedException;
use App\Message\DeclareConflict;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ConflictState;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Uses the conflict opportunity of that type, moves the fate on the contested ring to the attacking player's fate
 * pool, records the conflict and turns the attacked province faceup. The players can then react to the declaration.
 */
#[AsMessageHandler]
final readonly class DeclareConflictHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private ConflictService $conflictService,
        private WorkflowInterface $gameStateMachine,
        private WorkflowInterface $provinceCardStateMachine,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $game->assertPlace('declare_conflict');
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    public function __invoke(DeclareConflict $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $gameState = $this->gameStateManager->getState($game);

        if (! $gameState->isRingUnclaimed($message->getRing())) {
            throw new RingNotUnclaimedException($message->getRing()->value);
        }

        $province = $this->cardRepository->get($message->getProvinceId(), $game);
        $defender = $this->conflictService->assertAttackable($province, $player);

        $attackerIds = array_values(array_unique($message->getAttackerIds()));
        if ($attackerIds === []) {
            throw new NoAttackerException();
        }
        foreach ($attackerIds as $attackerId) {
            $this->conflictService->assertCanParticipate($this->cardRepository->get($attackerId, $game), $player);
        }

        $evadedIds = array_values(array_unique($message->getEvadedIds()));
        foreach ($evadedIds as $evadedId) {
            $this->conflictService->assertCharacterInPlay($this->cardRepository->get($evadedId, $game), $defender);
        }

        $ringFate = $gameState->getRing($message->getRing())->fate;
        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->useConflictOpportunity($message->getType())->incrementFate($ringFate),
        );
        $this->gameStateManager->withState($game, function (GameState $state) use ($message, $player, $defender, $attackerIds, $evadedIds): GameState {
            $state->getRing($message->getRing())->fate = 0;
            $state->conflict = new ConflictState($player->getId(), $defender->getId(), $message->getRing(), $message->getType(), $message->getProvinceId());
            $state->conflict->attackerIds = $attackerIds;
            $state->conflict->evadedIds = $evadedIds;
            $state->lastConflictPlayerId = $player->getId();

            return $state;
        });

        if ($province->getCurrentPlace() === ProvinceCard::STATE_FACEDOWN) {
            $this->provinceCardStateMachine->apply($province, 'reveal');
        }

        $this->gameStateMachine->apply($game, 'conflict_declared');
    }
}
