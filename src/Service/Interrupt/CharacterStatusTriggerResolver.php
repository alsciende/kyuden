<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\PersonalHonor;
use App\Exception\Data\DataException;
use App\Exception\Rules\RulesException;
use App\Message\ActionInterface;
use App\Message\DishonorCharacter;
use App\Message\HonorCharacter;
use App\Message\RemoveFate;
use App\Repository\CardRepository;
use App\Service\ConflictService;
use App\Service\ControlService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\Trigger\CharacterDishonoringTrigger;
use App\Trigger\CharacterHonoringTrigger;
use App\Trigger\FateRemovingTrigger;
use App\Workflow\Game\FateRemovedTransitionListener;

/**
 * A character in play would be honored, dishonored (HonorCharacter, DishonorCharacter), or lose fate (RemoveFate, and
 * each character with fate in the fate phase: `fate_removed`).
 */
final readonly class CharacterStatusTriggerResolver implements ImminentTriggerResolverInterface
{
    public function __construct(
        private CardRepository $cardRepository,
        private ConflictService $conflictService,
        private ControlService $controlService,
        private CardStateManager $cardStateManager,
        private GameStateManager $gameStateManager,
    ) {
    }

    #[\Override]
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        if (! $message instanceof HonorCharacter && ! $message instanceof DishonorCharacter && ! $message instanceof RemoveFate) {
            return [];
        }

        try {
            $game->getPlayer($message->getPlayerId());
            $card = $this->cardRepository->get($message->getCardId(), $game);
            $this->conflictService->assertCharacterInPlay($card);
        } catch (DataException|RulesException) {
            // refused by its handler: nothing would occur
            return [];
        }

        $state = $this->cardStateManager->getState($card);
        $controller = $this->controlService->getController($card);

        return match (true) {
            $message instanceof HonorCharacter => $state->getPersonalHonor() === PersonalHonor::Honored ? [] : [new CharacterHonoringTrigger($card, $controller)],
            $message instanceof DishonorCharacter => $state->getPersonalHonor() === PersonalHonor::Dishonored ? [] : [new CharacterDishonoringTrigger($card, $controller)],
            default => $message->getAmount() > 0 && $message->getAmount() <= $state->getFate() ? [new FateRemovingTrigger($card, $controller, $message->getAmount())] : [],
        };
    }

    #[\Override]
    public function resolveTransition(string $transition, Game $game): array
    {
        if ($transition !== 'fate_removed') {
            return [];
        }

        $triggers = [];
        foreach ($this->gameStateManager->getState($game)->playerIdsFromFirstPlayer() as $playerId) {
            foreach ($game->getPlayer($playerId)->getCharactersInPlay() as $character) {
                $removed = min(FateRemovedTransitionListener::FATE_REMOVED, $this->cardStateManager->getState($character)->getFate());
                if ($removed > 0) {
                    $triggers[] = $this->fateRemoving($character, $removed);
                }
            }
        }

        return $triggers;
    }

    private function fateRemoving(PhysicalCard $character, int $amount): FateRemovingTrigger
    {
        return new FateRemovingTrigger($character, $this->controlService->getController($character), $amount);
    }
}
