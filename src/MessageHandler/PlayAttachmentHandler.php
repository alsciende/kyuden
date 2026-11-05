<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Message\PlayAttachment;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\Service\OpportunityService;
use App\Service\PlayService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Allowed in the action windows where attachments can be played from hand, whether the attachment is in a hand or
 * elsewhere (PlayService: the effect allowing it is not checked). The player pays the cost of the attachment, which
 * joins the stack of the card it is attached to, under their control: a character in play of any player, or a
 * province. Attachment restrictions printed on cards are not checked. The player keeps the opportunity to act and
 * ends their action (EndResolution).
 */
#[AsMessageHandler]
final readonly class PlayAttachmentHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private OpportunityService $opportunityService,
        private PlayService $playService,
        private ConflictService $conflictService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->opportunityService->assertCanTakeAction($game, $player, playFromHand: true);
    }

    public function __invoke(PlayAttachment $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->playService->assertPlayable($card, Type::Attachment);

        $target = $this->cardRepository->get($message->getTargetCardId(), $game);
        if (! $target instanceof ProvinceCard) {
            $this->conflictService->assertCharacterInPlay($target);
        }

        $this->playService->payCost($player, $message->getCardCost());
        $this->playService->enterPlay($card, $player, [
            'stack' => $target->getStack(),
        ]);

        $this->opportunityService->takeAction($player);
    }
}
