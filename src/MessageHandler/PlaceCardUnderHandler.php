<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\CardTypes\StrongholdCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Exception\Rules\CannotHostCardException;
use App\Message\PlaceCardUnder;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ProvinceRefiller;
use App\Service\SetAsideStacks;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Accepted in any game place and from any player of the game, on any card: the engine does not apply card effects,
 * which decide what goes under a card. The host is a card in play (a character or an attachment, a holding face up in a
 * province, the stronghold) or a province, whoever owns it; the cards under it are in a stack of its owner, the only
 * player who sees the face of a card placed face down (Visibility), and are discarded when it leaves its place
 * (App\Workflow\HostLeaveListener). The `place_under` transitions of the conflict and dynasty card workflows fail for
 * the other cards (provinces, strongholds, roles) and for a card already under a card. A province a dynasty card leaves
 * is refilled once the effect is resolved, if it is still empty.
 */
#[AsMessageHandler]
final readonly class PlaceCardUnderHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private Registry $workflowRegistry,
        private SetAsideStacks $setAsideStacks,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(PlaceCardUnder $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $host = $this->cardRepository->get($message->getHostCardId(), $game);
        if ($host === $card || ! $this->canHost($host)) {
            throw new CannotHostCardException($host);
        }

        $from = $card->getStack();
        $this->workflowRegistry->get($card)->apply($card, $message->isFaceDown() ? 'place_under_facedown' : 'place_under', [
            // a stack created by a transition refused is rolled back with the rest
            'stack' => $this->setAsideStacks->underneath($host),
        ]);
        if ($card instanceof DynastyCard) {
            $this->provinceRefiller->schedule($from);
        }
    }

    /**
     * A card that stays where it is while cards are under it: one in play, or a province.
     */
    private function canHost(PhysicalCard $host): bool
    {
        return match (true) {
            $host instanceof ProvinceCard, $host instanceof StrongholdCard => true,
            $host instanceof ConflictCard => in_array($host->getCurrentPlace(), PhysicalCard::IN_PLAY_PLACES, true),
            $host instanceof DynastyCard => in_array($host->getCurrentPlace(), [...PhysicalCard::IN_PLAY_PLACES, DynastyCard::STATE_PROVINCE_FACEUP], true),
            default => false,
        };
    }
}
