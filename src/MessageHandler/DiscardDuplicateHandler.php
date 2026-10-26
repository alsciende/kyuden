<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Enum\Type;
use App\Event\FatePlacedOnCardEvent;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotADuplicateException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\DiscardDuplicate;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\Service\OpportunityService;
use App\Service\ProvinceRefiller;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Model\CardState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Step 1.4: the duplicate is discarded, 1 fate is placed on the copy in play, then the province is refilled
 * (ProvinceRefiller). Like playing a character from a province, the player keeps the opportunity to act and ends their
 * action (EndResolution) themselves, possibly after reactions to the fate placed.
 */
#[AsMessageHandler]
final readonly class DiscardDuplicateHandler implements PlayerActionHandlerInterface
{
    private const int FATE_PLACED = 1;

    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private CardStateManager $cardStateManager,
        private ConflictService $conflictService,
        private Registry $workflowRegistry,
        private EventDispatcherInterface $eventDispatcher,
        private OpportunityService $opportunityService,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $game->assertPlace('play_cards_from_provinces');
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    public function __invoke(DiscardDuplicate $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $copy = $this->cardRepository->get($message->getCopyId(), $game);
        $this->conflictService->assertCharacterInPlay($copy, $player);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->assertDuplicate($card, $copy, $player);

        $stack = $card->getStack();
        $this->workflowRegistry->get($card)->apply($card, 'discard_card');
        $this->cardStateManager->withState(
            $copy,
            fn (CardState $state): CardState => $state->incrementFate(self::FATE_PLACED),
        );
        $this->eventDispatcher->dispatch(new FatePlacedOnCardEvent($game, $copy, self::FATE_PLACED));
        if ($card instanceof DynastyCard) {
            $this->provinceRefiller->refillIfEmpty($stack);
        }

        $this->opportunityService->takeAction($player);
    }

    /**
     * A copy of a unique character with the same title (another version included), in the hand of the player or face
     * up in one of their provinces.
     */
    private function assertDuplicate(PhysicalCard $card, PhysicalCard $copy, Player $player): void
    {
        if ($card->getPlayer() !== $player) {
            throw new NotOwnedCardException($card, $player);
        }

        if ($card->getLogicalCard()->getType() !== Type::Character) {
            throw new WrongCardTypeException($card, Type::Character);
        }

        if ($card === $copy) {
            throw new NotADuplicateException($card, $copy, 'it is the same card');
        }

        if ($copy->getLogicalCard()->getUniqueness() !== true) {
            throw new NotADuplicateException($card, $copy, 'the character in play is not unique');
        }

        if ($card->getLogicalCard()->getName() !== $copy->getLogicalCard()->getName()) {
            throw new NotADuplicateException($card, $copy, 'their titles differ');
        }

        $inHand = $card->getStack()->getType() === StackType::Hand;
        $faceupInProvince = in_array($card->getStack()->getType(), [StackType::RegularProvince, StackType::StrongholdProvince], true)
            && $card->getOrientation() === Orientation::FaceUp;
        if (! $inHand && ! $faceupInProvince) {
            throw new NotADuplicateException($card, $copy, 'it is neither in hand nor face up in a province');
        }
    }
}
