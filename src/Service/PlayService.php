<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\AbilityTiming;
use App\Enum\Type;
use App\Event\EventCardPlayedEvent;
use App\Event\FateSpentEvent;
use App\Exception\Rules\NotPlayableException;
use App\Exception\Rules\WrongAbilityTimingException;
use App\Exception\Rules\WrongCardTypeException;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Rules about playing a card during an action window (PlayCharacter, PlayAttachment and the events), from its hand or
 * from elsewhere: a card of any player, from a hand, a deck or a discard pile (a conflict card), from a province face
 * up, a deck or a discard pile (a dynasty character). The engine does not check the effect allowing it (Artisan
 * Academy, Kunshu, Prepared Ambush…): the player playing it controls it, and an event goes to the discard pile of its
 * owner.
 */
readonly class PlayService
{
    /**
     * The places a card is played from, by class.
     */
    private const array PLAYABLE_PLACES = [
        ConflictCard::class => [ConflictCard::STATE_HAND, ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_DISCARD_PILE],
        DynastyCard::class => [DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_DISCARD_PILE],
    ];

    public function __construct(
        private PlayerStateManager $playerStateManager,
        #[Target('conflict_card')]
        private WorkflowInterface $conflictCardStateMachine,
        private Registry $workflowRegistry,
        private EventDispatcherInterface $eventDispatcher,
        private CardScriptRegistry $cardScriptRegistry,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    /**
     * A card of that type, where a card of its class is played from.
     *
     * @throws WrongCardTypeException
     * @throws NotPlayableException
     */
    public function assertPlayable(PhysicalCard $card, Type $type): void
    {
        if ($card->getLogicalCard()->getType() !== $type) {
            throw new WrongCardTypeException($card, $type);
        }

        if (! in_array($card->getCurrentPlace(), self::PLAYABLE_PLACES[$card::class] ?? [], true)) {
            throw new NotPlayableException($card);
        }
    }

    /**
     * The player pays fate for what they play.
     */
    public function payCost(Player $player, int $amount): void
    {
        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->spendFate($amount),
        );
        if ($amount > 0) {
            $this->eventDispatcher->dispatch(new FateSpentEvent($player->getGame(), $player, $amount));
        }
    }

    /**
     * The player pays the cost of an event with an ability of that timing; the event goes to the discard pile of its
     * owner, and its effects are resolved with other actions.
     */
    public function playEvent(PhysicalCard $card, Player $player, int $cardCost, AbilityTiming $timing): void
    {
        $this->assertPlayable($card, Type::Event);
        if (! $this->cardScriptRegistry->hasAbilityTiming($card->getLogicalCard()->getId(), $timing)) {
            throw new WrongAbilityTimingException($card, $timing);
        }

        $this->payCost($player, $cardCost);
        $this->eventDispatcher->dispatch(new EventCardPlayedEvent($player->getGame(), $player, $card));
        $this->conflictCardStateMachine->apply($card, 'play_event');
    }

    /**
     * A character or an attachment enters play, played by the player, who controls it: under the control of its owner
     * when they play it, else it changes control once in play (`take_control`). A province it leaves is refilled once
     * the action is ended, if still empty.
     *
     * @param array<string, mixed> $context the context of the transition (the `stack` of an attachment)
     */
    public function enterPlay(PhysicalCard $card, Player $player, array $context = []): void
    {
        $from = $card->getStack();
        $workflow = $this->workflowRegistry->get($card);
        $workflow->apply($card, $card instanceof DynastyCard ? 'play' : 'play_card', [
            ...$context,
            'player' => $player,
        ]);
        if ($player !== $card->getPlayer()) {
            $workflow->apply($card, 'take_control', [
                'controller' => $player,
            ]);
        }

        $this->provinceRefiller->schedule($from);
    }
}
