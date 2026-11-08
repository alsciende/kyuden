<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Enum\Type;
use App\Event\ProvinceCardsRedistributedEvent;
use App\Exception\Rules\CannotFillProvinceException;
use App\Exception\Rules\NotInProvinceException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardTypeException;
use App\Factory\CardFactory;
use App\Message\RedistributeProvinceCards;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ProvinceRefiller;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on the dynasty cards of the regular provinces of any
 * player: the engine does not apply card effects. The face-up cards are turned face down where they are (transition
 * `turn_facedown`: every player saw them there; a holding returns its honor tokens, the cards under it are discarded).
 * Then the cards are picked up and put back face down into the provinces given, as new cards (same logical card and
 * owner, new ids drawn from the generator of the game, fresh state, on top of the province in the order of the
 * message), the way `StackShuffler` makes the players lose track of the cards of a deck: moving them by transitions
 * would log where each card went. A province left without a dynasty card is refilled once the effect is resolved.
 */
#[AsMessageHandler]
final readonly class RedistributeProvinceCardsHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private CardFactory $cardFactory,
        private ProvinceRefiller $provinceRefiller,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        #[Target('dynasty_card')]
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(RedistributeProvinceCards $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);
        if (count($message->getCardIds()) !== count($message->getProvinceIds())) {
            throw new \InvalidArgumentException('Each card needs a province: card_ids and province_ids must have the same length.');
        }

        /** @var list<array{DynastyCard, Stack}> $placements */
        $placements = [];
        foreach ($message->getCardIds() as $index => $cardId) {
            $placements[] = [
                $this->assertCardOf($this->cardRepository->get($cardId, $game), $target),
                $this->assertProvinceOf($this->cardRepository->get($message->getProvinceIds()[$index], $game), $target),
            ];
        }

        // the face-up cards are turned face down where they are, as the players see it
        foreach ($placements as [$card]) {
            if ($card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEUP) {
                $this->dynastyCardStateMachine->apply($card, 'turn_facedown');
            }
        }

        // then picked up: deleted, every one before any is put back, so that the positions are counted without them
        /** @var array<string, Stack> $stacks */
        $stacks = [];
        foreach ($placements as [$card, $province]) {
            $from = $card->getStack();
            $stacks[$from->getId()] = $from;
            $stacks[$province->getId()] = $province;
            $from->removeCard($card);
            $card->getPlayer()->getPhysicalCards()->removeElement($card);
            $game->getPhysicalCards()->removeElement($card);
            $this->entityManager->remove($card);
        }

        foreach ($stacks as $stack) {
            foreach ($stack->getOrderedCards() as $position => $card) {
                $card->setPosition($position + 1);
            }
        }

        // and put back, each into its province
        foreach ($placements as [$card, $province]) {
            $this->cardFactory->createCard($game->generateId(), $card->getLogicalCard(), $province, Orientation::FaceDown);
        }

        foreach ($stacks as $stack) {
            // a province left without a dynasty card
            $this->provinceRefiller->schedule($stack);
        }

        $this->eventDispatcher->dispatch(new ProvinceCardsRedistributedEvent($game, $player, $target));
    }

    private function assertCardOf(PhysicalCard $card, Player $target): DynastyCard
    {
        if (! $card instanceof DynastyCard || $card->getStack()->getType() !== StackType::RegularProvince) {
            throw new NotInProvinceException($card);
        }

        if ($card->getPlayer() !== $target) {
            throw new NotOwnedCardException($card, $target);
        }

        return $card;
    }

    private function assertProvinceOf(PhysicalCard $province, Player $target): Stack
    {
        if (! $province instanceof ProvinceCard) {
            throw new WrongCardTypeException($province, Type::Province);
        }

        if ($province->getStack()->getType() !== StackType::RegularProvince) {
            throw new CannotFillProvinceException($province, 'the cards are redistributed among the regular provinces');
        }

        if ($province->getPlayer() !== $target) {
            throw new NotOwnedCardException($province, $target);
        }

        return $province->getStack();
    }
}
