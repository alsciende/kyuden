<?php

declare(strict_types=1);

namespace App\Service\Bot;

use App\Entity\Event;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Enum\StackType;
use App\Message\BowCard;
use App\Message\DiscardRandomCardFromHand;
use App\Message\DishonorCharacter;
use App\Message\DrawConflictCards;
use App\Message\GainHonor;
use App\Message\HonorCharacter;
use App\Message\PassOpportunity;
use App\Message\PlayerActionInterface;
use App\Message\ReadyCard;
use App\Message\RemoveFate;
use App\Repository\EventRepository;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;

/**
 * The effect of the contested ring, resolved by a bot that has won a conflict as the attacking player (3.2.6): the
 * messages of the effect, one at a time, then nothing once it is resolved.
 *
 * The engine does not record that the effect is resolved: the bot reads its own messages since the step began, in
 * the events of the game. Only the attacking player is active in this step, so the step began with the last
 * PassOpportunity (that of the reaction window before it).
 */
final readonly class RingEffectChooser
{
    public const int AIR_HONOR_GAIN = 2;

    /**
     * At most this many events are read back to the beginning of the step.
     */
    private const int MAX_EVENTS = 50;

    public function __construct(
        private GameStateManager $gameStateManager,
        private CardStateManager $cardStateManager,
        private EventRepository $eventRepository,
        private BoardReader $boardReader,
    ) {
    }

    /**
     * @return PlayerActionInterface|null the next message of the effect, null once it is resolved or has no target
     */
    public function nextAction(Game $game, Player $player, Player $opponent): ?PlayerActionInterface
    {
        $sent = $this->sentSinceTheStepBegan($game, $player);
        $gameId = $game->getId();
        $playerId = $player->getId();
        $element = $this->gameStateManager->getState($game)->currentConflict()->ring;

        if ($element === Element::Earth) {
            if (! in_array(DrawConflictCards::class, $sent, true)) {
                return new DrawConflictCards($gameId, $playerId, $playerId, 1);
            }

            return in_array(DiscardRandomCardFromHand::class, $sent, true) || $opponent->getUniqueStack(StackType::Hand)->getCards()->isEmpty()
                ? null
                : new DiscardRandomCardFromHand($gameId, $playerId, $opponent->getId());
        }

        if ($sent !== []) {
            return null;
        }

        return match ($element) {
            Element::Air => new GainHonor($gameId, $playerId, $playerId, self::AIR_HONOR_GAIN),
            Element::Fire => $this->fire($game, $player, $opponent),
            Element::Water => $this->water($game, $player, $opponent),
            Element::Void => $this->void($game, $player, $opponent),
        };
    }

    /**
     * Honors its character of the highest glory, else dishonors that of the opponent.
     */
    private function fire(Game $game, Player $player, Player $opponent): ?PlayerActionInterface
    {
        $own = $this->mostGlorious($player, PersonalHonor::Honored);
        $theirs = $this->mostGlorious($opponent, PersonalHonor::Dishonored);
        if ($own instanceof PhysicalCard && ($own->getLogicalCard()->getGlory() ?? 0) >= ($theirs?->getLogicalCard()->getGlory() ?? 0)) {
            return new HonorCharacter($game->getId(), $player->getId(), $own->getId());
        }

        return $theirs instanceof PhysicalCard ? new DishonorCharacter($game->getId(), $player->getId(), $theirs->getId()) : null;
    }

    /**
     * Bows the strongest ready character of the opponent with no fate, else readies its strongest bowed character.
     */
    private function water(Game $game, Player $player, Player $opponent): ?PlayerActionInterface
    {
        $toBow = $this->strongest(array_filter(
            $opponent->getCharactersInPlay(),
            fn (PhysicalCard $character): bool => $character->getOrientation() === Orientation::FaceUp && $this->cardStateManager->getState($character)->getFate() === 0,
        ));
        if ($toBow instanceof PhysicalCard) {
            return new BowCard($game->getId(), $player->getId(), $toBow->getId());
        }

        $toReady = $this->strongest(array_filter(
            $player->getCharactersInPlay(),
            fn (PhysicalCard $character): bool => $character->getOrientation() === Orientation::Sideways,
        ));

        return $toReady instanceof PhysicalCard ? new ReadyCard($game->getId(), $player->getId(), $toReady->getId()) : null;
    }

    /**
     * Removes the last fate of a character of the opponent, else 1 fate from their most expensive character with fate.
     */
    private function void(Game $game, Player $player, Player $opponent): ?PlayerActionInterface
    {
        $characters = array_values(array_filter(
            $opponent->getCharactersInPlay(),
            fn (PhysicalCard $character): bool => $this->cardStateManager->getState($character)->getFate() > 0,
        ));
        usort($characters, fn (PhysicalCard $a, PhysicalCard $b): int => [$this->cardStateManager->getState($a)->getFate() === 1, $a->getLogicalCard()->getCost()] <=> [$this->cardStateManager->getState($b)->getFate() === 1, $b->getLogicalCard()->getCost()]);
        $character = array_pop($characters);

        return $character instanceof PhysicalCard ? new RemoveFate($game->getId(), $player->getId(), $character->getId(), 1) : null;
    }

    /**
     * The character of the player with the highest glory whose personal honor is not already that status.
     */
    private function mostGlorious(Player $player, PersonalHonor $status): ?PhysicalCard
    {
        $characters = array_values(array_filter(
            $player->getCharactersInPlay(),
            fn (PhysicalCard $character): bool => $this->cardStateManager->getState($character)->getPersonalHonor() !== $status && ($character->getLogicalCard()->getGlory() ?? 0) > 0,
        ));
        usort($characters, fn (PhysicalCard $a, PhysicalCard $b): int => $b->getLogicalCard()->getGlory() <=> $a->getLogicalCard()->getGlory() ?: $a->getId() <=> $b->getId());

        return $characters[0] ?? null;
    }

    /**
     * @param array<PhysicalCard> $characters
     */
    private function strongest(array $characters): ?PhysicalCard
    {
        $characters = array_values($characters);
        $skill = fn (PhysicalCard $character): int => $this->boardReader->skillOrZero($character, ConflictType::Military) + $this->boardReader->skillOrZero($character, ConflictType::Political);
        usort($characters, fn (PhysicalCard $a, PhysicalCard $b): int => $skill($b) <=> $skill($a) ?: $a->getId() <=> $b->getId());

        return $characters[0] ?? null;
    }

    /**
     * The types of the messages the player has sent since the step began.
     *
     * @return list<class-string>
     */
    private function sentSinceTheStepBegan(Game $game, Player $player): array
    {
        $sent = [];
        foreach ($this->eventRepository->findLatest($game, self::MAX_EVENTS) as $event) {
            if ($event->getName() === PassOpportunity::class) {
                break;
            }

            if ($this->senderOf($event) === $player->getId()) {
                /** @var class-string $name */
                $name = $event->getName();
                $sent[] = $name;
            }
        }

        return $sent;
    }

    private function senderOf(Event $event): ?string
    {
        $payload = json_decode($event->getPayload(), true);

        return is_array($payload) && is_string($payload['player_id'] ?? null) ? $payload['player_id'] : null;
    }
}
