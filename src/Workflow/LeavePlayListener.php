<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\PhysicalCard;
use App\Enum\PersonalHonor;
use App\Event\FateRemovedFromCardEvent;
use App\Event\HonorGainedEvent;
use App\Event\HonorLostEvent;
use App\Event\PersonalHonorChangedEvent;
use App\Service\ControlService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\CardState;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\LeaveEvent;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;

/**
 * Consequences of a card leaving play ("Leaves Play" in the glossary), for any card transition from `in_play` to
 * another place (discarded, returned to hand…), or from `attached` (a character in play as an attachment):
 * - the controller of an honored character gains 1 honor, of a dishonored one loses 1 honor;
 * - the tokens on the card return to the general token pool and the control by another player ends (its CardState is
 *   reset): the card goes to an out-of-play area of its owner;
 * - the attachments on a character are discarded, the characters attached to it as attachments too (ancestral
 *   attachments are not returned to hand);
 * - the character no longer participates in the conflict.
 */
#[AsEventListener(event: 'workflow.leave')]
readonly class LeavePlayListener
{
    public const int PERSONAL_HONOR_GAIN = 1;

    public function __construct(
        private CardStateManager $cardStateManager,
        private ControlService $controlService,
        private PlayerStateManager $playerStateManager,
        private GameStateManager $gameStateManager,
        private Registry $workflowRegistry,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param LeaveEvent<object> $event
     */
    public function __invoke(LeaveEvent $event): void
    {
        $card = $event->getSubject();
        $transition = $event->getTransition();
        // every card workflow names its in-play places the same way; an attachment turned into a character (detach) stays
        // in play
        if (! $card instanceof PhysicalCard
            || ! in_array($card->getCurrentPlace(), PhysicalCard::IN_PLAY_PLACES, true)
            || ! $transition instanceof Transition
            || PhysicalCard::hasInPlayPlace($transition->getTos())) {
            return;
        }

        $cardState = $this->cardStateManager->getState($card);
        $honorChange = match ($cardState->getPersonalHonor()) {
            PersonalHonor::Honored => self::PERSONAL_HONOR_GAIN,
            PersonalHonor::Dishonored => -self::PERSONAL_HONOR_GAIN,
            PersonalHonor::Ordinary => 0,
        };
        if ($honorChange !== 0) {
            $controller = $this->controlService->getController($card);
            $this->playerStateManager->withState(
                $controller,
                fn (PlayerState $state): PlayerState => $state->incrementHonor($honorChange),
            );
            $this->eventDispatcher->dispatch($honorChange > 0
                ? new HonorGainedEvent($card->getGame(), $controller, $honorChange)
                : new HonorLostEvent($card->getGame(), $controller, -$honorChange));
        }

        $this->cardStateManager->setState($card, new CardState());
        if ($cardState->getFate() > 0) {
            $this->eventDispatcher->dispatch(new FateRemovedFromCardEvent($card->getGame(), $card, $cardState->getFate()));
        }

        if ($cardState->getPersonalHonor() !== PersonalHonor::Ordinary) {
            $this->eventDispatcher->dispatch(new PersonalHonorChangedEvent($card->getGame(), $card, PersonalHonor::Ordinary));
        }

        if ($card->isCharacterInPlay()) {
            foreach ($card->getStack()->getCards()->getValues() as $attachment) {
                if ($attachment !== $card) {
                    $this->workflowRegistry->get($attachment)->apply($attachment, 'discard_card');
                }
            }
        }

        $this->gameStateManager->withState($card->getGame(), function (GameState $state) use ($card): GameState {
            if ($state->conflict !== null) {
                $state->conflict->attackerIds = array_values(array_diff($state->conflict->attackerIds, [$card->getId()]));
                $state->conflict->defenderIds = array_values(array_diff($state->conflict->defenderIds, [$card->getId()]));
            }

            return $state;
        });
    }
}
