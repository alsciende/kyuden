<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Enum\Type;
use App\Message\PlayAttachment;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\Service\HandService;
use App\Service\OpportunityService;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Allowed in the action windows where attachments can be played from hand. The player pays the cost of the attachment,
 * which joins the stack of the card it is attached to: a character in play of any player, or a province. Attachment
 * restrictions printed on cards are not checked. The player keeps the opportunity to act and ends it (EndOpportunity).
 */
#[AsMessageHandler]
final readonly class PlayAttachmentHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private PlayerStateManager $playerStateManager,
        private OpportunityService $opportunityService,
        private HandService $handService,
        private ConflictService $conflictService,
        private WorkflowInterface $conflictCardStateMachine,
    ) {
    }

    public function __invoke(PlayAttachment $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->opportunityService->assertCanTakeAction($game, $player, playFromHand: true);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->handService->assertPlayable($card, $player, Type::Attachment);

        $target = $this->cardRepository->get($message->getTargetCardId(), $game);
        if (! $target instanceof ProvinceCard) {
            $this->conflictService->assertCharacterInPlay($target);
        }

        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->spendFate($message->getCardCost()),
        );
        $this->conflictCardStateMachine->apply($card, 'play_card', [
            'stack' => $target->getStack(),
        ]);
    }
}
