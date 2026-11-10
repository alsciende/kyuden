<?php

declare(strict_types=1);

namespace App\Service\Abandonment;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\StackType;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\AttackedProvinceFacedownException;
use App\Exception\Rules\RulesException;
use App\Message\BidHonor;
use App\Message\ChooseStrongholdProvince;
use App\Message\ClaimImperialFavor;
use App\Message\CollectFate;
use App\Message\DeclareConflict;
use App\Message\DeclareConflictSkill;
use App\Message\DeclareDefenders;
use App\Message\DeclareDuelStatistic;
use App\Message\DeclareGloryCount;
use App\Message\DeclareProvinceStrength;
use App\Message\EndDuel;
use App\Message\EndResolution;
use App\Message\MulliganConflictCards;
use App\Message\MulliganDynastyCards;
use App\Message\PassConflict;
use App\Message\PassOpportunity;
use App\Service\AvailableActionsResolver;
use App\State\Manager\GameStateManager;

/**
 * The players the game waits for: those who must act for it to move on. Read from the state only, the same for every
 * reader. A player is awaited when an action moving the game on is available to them (their handlers say so, see
 * AvailableActionsResolver): passing their opportunity, ending what they resolve, bidding, declaring a conflict, its
 * defenders, a total, a dueling statistic, ending a duel…; and in the two steps that end once the cards are face up,
 * the defending player while the attacked province is face down, the players with a face-down dynasty card in a
 * province while they are revealed; a player who would pass or end what they resolve but for a facedown province the
 * conflict has moved to, which they reveal. The effects on the board, available to every player in every step, move
 * nothing on.
 */
final readonly class AwaitedPlayersResolver
{
    /**
     * @var list<class-string>
     */
    private const array MOVING_ON = [
        PassOpportunity::class,
        EndResolution::class,
        BidHonor::class,
        ChooseStrongholdProvince::class,
        MulliganDynastyCards::class,
        MulliganConflictCards::class,
        CollectFate::class,
        DeclareConflict::class,
        PassConflict::class,
        DeclareDefenders::class,
        DeclareConflictSkill::class,
        DeclareProvinceStrength::class,
        DeclareGloryCount::class,
        ClaimImperialFavor::class,
        DeclareDuelStatistic::class,
        EndDuel::class,
    ];

    public function __construct(
        private AvailableActionsResolver $availableActionsResolver,
        private GameStateManager $gameStateManager,
    ) {
    }

    /**
     * @return list<Player> in player order; none before the game starts and once it has ended
     */
    public function resolve(Game $game): array
    {
        if (in_array($game->getCurrentPlace(), [null, 'join_game', Game::STATE_ENDED], true)) {
            return [];
        }

        $state = $this->gameStateManager->getState($game);

        return array_values(array_filter(
            array_map($game->getPlayer(...), $state->playerIds),
            fn (Player $player): bool => $this->isAwaited($game, $player),
        ));
    }

    private function isAwaited(Game $game, Player $player): bool
    {
        if (match ($game->getCurrentPlace()) {
            'reveal_attacked_province' => $this->gameStateManager->getState($game)->isActivePlayer($player),
            'reveal_facedown_dynasty_cards' => $this->hasFacedownDynastyCard($player),
            default => false,
        }) {
            return true;
        }

        foreach (self::MOVING_ON as $messageClass) {
            try {
                $this->availableActionsResolver->getHandler($messageClass)->assertAvailable($game, $player);

                return true;
            } catch (AttackedProvinceFacedownException) {
                // they would move the game on, once they have revealed the province the conflict is moved to (checked last)
                return true;
            } catch (RulesException|WorkflowException) {
            }
        }

        return false;
    }

    private function hasFacedownDynastyCard(Player $player): bool
    {
        foreach ($player->getStacks(StackType::RegularProvince) as $province) {
            foreach ($province->getCards() as $card) {
                if ($card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEDOWN) {
                    return true;
                }
            }
        }

        return false;
    }
}
