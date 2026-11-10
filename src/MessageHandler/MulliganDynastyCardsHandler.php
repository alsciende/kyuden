<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\HiddenPile;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Exception\Rules\NotInProvinceException;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\MulliganDynastyCards;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\CardInspector;
use App\Service\MulliganService;
use App\Service\ProvinceRefiller;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Setup 6: the active player mulligans face-down dynasty cards of their regular provinces. Each province of a card
 * chosen is filled from the top of the dynasty deck first, then the cards chosen are shuffled back into it (as if set
 * aside meanwhile). The player then looks at the cards of their provinces: the log gives them the new cards, which they
 * may look at before drawing their starting hand.
 */
#[AsMessageHandler]
final readonly class MulliganDynastyCardsHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private MulliganService $mulliganService,
        private ProvinceRefiller $provinceRefiller,
        private CardInspector $cardInspector,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->mulliganService->assertAvailable($game, $player, 'dynasty_mulligan');
    }

    public function __invoke(MulliganDynastyCards $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $cards = array_map(
            fn (string $cardId): DynastyCard => $this->assertMulliganable($this->cardRepository->get($cardId, $game), $player),
            $message->getCardIds(),
        );

        foreach ($cards as $card) {
            $this->provinceRefiller->fill($this->provinceOf($card));
        }

        $this->mulliganService->shuffleBack($player, $cards, $player->getUniqueStack(StackType::DynastyDrawDeck));
        if ($cards !== []) {
            $this->cardInspector->lookAtPile($player, $player, HiddenPile::Provinces, null);
        }
    }

    private function assertMulliganable(PhysicalCard $card, Player $player): DynastyCard
    {
        if (! $card instanceof DynastyCard || $card->getStack()->getType() !== StackType::RegularProvince || $card->getOrientation() !== Orientation::FaceDown) {
            throw new NotInProvinceException($card);
        }

        if ($card->getPlayer() !== $player) {
            throw new NotOwnedCardException($card, $player);
        }

        return $card;
    }

    private function provinceOf(DynastyCard $card): ProvinceCard
    {
        foreach ($card->getStack()->getCards() as $stackCard) {
            if ($stackCard instanceof ProvinceCard) {
                return $stackCard;
            }
        }

        throw new \LogicException(sprintf('The stack of card [%s] has no province.', $card->getId()));
    }
}
