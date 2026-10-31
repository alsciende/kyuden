<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Exception\Rules\AlreadyAttachedException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\MoveAttachment;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Accepted in any game place and from any player of the game, on any attachment: the engine does not apply card
 * effects, which decide where an attachment goes. The `move_attachment` transition fails for an attachment that is not
 * in play. The target is a character in play or a province, as for `PlayAttachment`.
 */
#[AsMessageHandler]
final readonly class MoveAttachmentHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private ConflictService $conflictService,
        private Registry $workflowRegistry,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(MoveAttachment $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        if ($card->getLogicalCard()->getType() !== Type::Attachment) {
            throw new WrongCardTypeException($card, Type::Attachment);
        }

        $target = $this->cardRepository->get($message->getTargetCardId(), $game);
        if (! $target instanceof ProvinceCard) {
            $this->conflictService->assertCharacterInPlay($target);
        }

        if ($target->getStack() === $card->getStack()) {
            throw new AlreadyAttachedException($card, $target);
        }

        $this->workflowRegistry->get($card)->apply($card, 'move_attachment', [
            'stack' => $target->getStack(),
        ]);
    }
}
