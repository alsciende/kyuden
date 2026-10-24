<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\SacrificeCard;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ProvinceRefiller;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Accepted in any game place and from any player of the game, on any card: the engine does not apply card effects,
 * which decide what is sacrificed. The `sacrifice` transition of the card workflow fails for cards that are not in play
 * (in hand, a character in a province) or cannot be sacrificed (provinces, strongholds, roles). A province a holding
 * is sacrificed from is then refilled (ProvinceRefiller).
 */
#[AsMessageHandler]
final readonly class SacrificeCardHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private Registry $workflowRegistry,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(SacrificeCard $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);

        $stack = $card->getStack();
        $this->workflowRegistry->get($card)->apply($card, 'sacrifice');
        if ($card instanceof DynastyCard) {
            $this->provinceRefiller->refillIfEmpty($stack);
        }
    }
}
