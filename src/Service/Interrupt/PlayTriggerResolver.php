<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\AbilityTiming;
use App\Enum\Type;
use App\Exception\Data\DataException;
use App\Exception\Rules\RulesException;
use App\Message\ActionInterface;
use App\Message\PlayActionEvent;
use App\Message\PlayAttachment;
use App\Message\PlayCharacterAsAttachment;
use App\Message\PlayerActionInterface;
use App\Message\PlayInterruptEvent;
use App\Message\PlayReactionEvent;
use App\Message\TriggerActionAbility;
use App\Message\TriggerInterruptAbility;
use App\Message\TriggerReactionAbility;
use App\Repository\CardRepository;
use App\Service\AbilityService;
use App\Service\AvailableActionsResolver;
use App\Service\CardScript\CardScriptRegistry;
use App\Service\PlayService;
use App\Trigger\AttachmentBeingPlayed;
use App\Trigger\EffectsInitiating;
use Symfony\Component\Workflow\Exception\ExceptionInterface as WorkflowException;

/**
 * The effects of a card would initiate (EffectsInitiating): an event played, an ability triggered; an attachment would
 * be played onto a character (AttachmentBeingPlayed). Checked as their handlers would, so that no window opens for a
 * play the engine would refuse.
 */
final readonly class PlayTriggerResolver implements ImminentTriggerResolverInterface
{
    /**
     * The events played, by the timing of their message.
     */
    private const array EVENTS = [
        PlayActionEvent::class => AbilityTiming::Action,
        PlayInterruptEvent::class => AbilityTiming::Interrupt,
        PlayReactionEvent::class => AbilityTiming::Reaction,
    ];

    /**
     * The abilities triggered, by the timing of their message.
     */
    private const array ABILITIES = [
        TriggerActionAbility::class => AbilityTiming::Action,
        TriggerInterruptAbility::class => AbilityTiming::Interrupt,
        TriggerReactionAbility::class => AbilityTiming::Reaction,
    ];

    public function __construct(
        private CardRepository $cardRepository,
        private AvailableActionsResolver $availableActionsResolver,
        private PlayService $playService,
        private AbilityService $abilityService,
        private CardScriptRegistry $cardScriptRegistry,
    ) {
    }

    #[\Override]
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        $class = $message::class;
        if (! $message instanceof PlayerActionInterface
            || (! isset(self::EVENTS[$class]) && ! isset(self::ABILITIES[$class]) && ! $message instanceof PlayAttachment && ! $message instanceof PlayCharacterAsAttachment)) {
            return [];
        }

        \assert(method_exists($message, 'getCardId'));
        $cardId = $message->getCardId();
        \assert(is_string($cardId));
        try {
            $player = $game->getPlayer($message->getPlayerId());
            $this->availableActionsResolver->getHandler($class)->assertAvailable($game, $player);
            $card = $this->cardRepository->get($cardId, $game);
            if ($message instanceof PlayAttachment || $message instanceof PlayCharacterAsAttachment) {
                return $this->attachment($card, $player, $message->getTargetCardId(), $game, $message instanceof PlayAttachment);
            }

            if (isset(self::EVENTS[$class])) {
                $this->playService->assertEventPlayable($card);
                if (! $this->cardScriptRegistry->hasAbilityTiming($card->getLogicalCard()->getId(), self::EVENTS[$class])) {
                    return [];
                }

                return [new EffectsInitiating($card, $player, true, self::EVENTS[$class])];
            }
        } catch (DataException|RulesException|WorkflowException) {
            // refused by its handler: nothing would occur
            return [];
        }

        return $this->abilityService->canTrigger($card, $player) ? [new EffectsInitiating($card, $player, false, self::ABILITIES[$class])] : [];
    }

    #[\Override]
    public function resolveTransition(string $transition, Game $game): array
    {
        return [];
    }

    /**
     * @return list<AttachmentBeingPlayed>
     */
    private function attachment(PhysicalCard $card, Player $player, string $targetId, Game $game, bool $attachment): array
    {
        $this->playService->assertPlayable($card, $attachment ? Type::Attachment : Type::Character);
        $target = $this->cardRepository->get($targetId, $game);

        return $target->isCharacterInPlay() ? [new AttachmentBeingPlayed($card, $player, $target)] : [];
    }
}
