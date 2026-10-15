<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\PassingRule;
use App\Event\DuelInitiatedEvent;
use App\Exception\Rules\CannotDuelException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInOpportunitiesStepException;
use App\Exception\Rules\NotParticipatingException;
use App\Message\InitiateDuel;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\Service\OpportunityService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\DuelState;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Allowed in any game place marked `opportunities`, when the player has the opportunity to act and no duel is being
 * resolved: the engine does not know the ability that initiates the duel. Checked (D.1): characters in play, the
 * challengers controlled by the player and the challenged characters by a single opponent; during a conflict, every
 * character participates. A new honor bid begins for the players involved.
 */
#[AsMessageHandler]
final readonly class InitiateDuelHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private OpportunityService $opportunityService,
        private ConflictService $conflictService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        if (! $this->opportunityService->getPassingRule($game) instanceof PassingRule) {
            throw new NotInOpportunitiesStepException($game->getCurrentPlace());
        }

        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }

        $this->opportunityService->assertNoDuel($game);
    }

    public function __invoke(InitiateDuel $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $gameState = $this->gameStateManager->getState($game);

        $challengers = $this->getCharacters($message->getChallengerIds(), $game);
        $challenged = $this->getCharacters($message->getChallengedIds(), $game);
        $opponent = $challenged[0]->getPlayer();
        if ($opponent === $player) {
            throw new CannotDuelException($challenged[0], 'it is controlled by the player initiating the duel');
        }

        foreach ($challengers as $card) {
            $this->conflictService->assertCharacterInPlay($card, $player);
        }
        foreach ($challenged as $card) {
            $this->conflictService->assertCharacterInPlay($card, $opponent);
        }

        $conflict = $gameState->conflict;
        if ($conflict !== null) {
            foreach ([...$challengers, ...$challenged] as $card) {
                if (! in_array($card->getId(), [...$conflict->attackerIds, ...$conflict->defenderIds], true)) {
                    throw new NotParticipatingException($card);
                }
            }
        }

        foreach ([$player, $opponent] as $duelist) {
            $this->playerStateManager->withState(
                $duelist,
                fn (PlayerState $state): PlayerState => $state->startHonorBid(),
            );
        }
        $this->gameStateManager->withState($game, function (GameState $state) use ($message, $player, $opponent, $challengers, $challenged): GameState {
            $state->duel = new DuelState($player->getId(), $opponent->getId(), $message->getType());
            $state->duel->challengerCardIds = $this->ids($challengers);
            $state->duel->challengedCardIds = $this->ids($challenged);

            return $state;
        });
        $this->eventDispatcher->dispatch(new DuelInitiatedEvent($game, $player, $opponent, $message->getType(), $challengers, $challenged));
    }

    /**
     * @param list<string> $ids
     *
     * @return non-empty-list<PhysicalCard>
     */
    private function getCharacters(array $ids, Game $game): array
    {
        $cards = array_map(fn (string $id): PhysicalCard => $this->cardRepository->get($id, $game), array_values(array_unique($ids)));
        if ($cards === []) {
            throw new \InvalidArgumentException('Each side of a duel needs at least one character.');
        }

        return $cards;
    }

    /**
     * @param list<PhysicalCard> $cards
     *
     * @return list<string>
     */
    private function ids(array $cards): array
    {
        return array_map(fn (PhysicalCard $card): string => $card->getId(), $cards);
    }
}
