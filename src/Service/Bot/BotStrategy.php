<?php

declare(strict_types=1);

namespace App\Service\Bot;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\StackType;
use App\Enum\Type;
use App\Exception\Rules\ProvinceNotAttackableException;
use App\Message\BidHonor;
use App\Message\ChooseStrongholdProvince;
use App\Message\ClaimImperialFavor;
use App\Message\CollectFate;
use App\Message\DeclareConflict;
use App\Message\DeclareConflictSkill;
use App\Message\DeclareDefenders;
use App\Message\DeclareDuelStatistic;
use App\Message\DeclareGloryCount;
use App\Message\DeclareNoDefenders;
use App\Message\EndResolution;
use App\Message\PassConflict;
use App\Message\PassOpportunity;
use App\Message\PlayCharacterFromProvince;
use App\Message\PlayerActionInterface;
use App\Message\RevealDynastyCard;
use App\Message\RevealProvince;
use App\Service\AvailableActionsResolver;
use App\Service\ConflictService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\DuelState;

/**
 * Chooses the next action of a bot from the actions available to it (AvailableActionsResolver), so that it never
 * sends what the engine would refuse in the current step.
 *
 * The bot plays no conflict card and triggers no ability: it plays characters from its provinces, declares conflicts
 * and defenders, resolves the ring effects (RingEffectChooser), takes part in the duels and does what the framework
 * steps require, reading the board like the web
 * client prefills the totals (BoardReader). It sees what a player sees: the face-down provinces of its opponent have
 * an assumed strength, and their hand is unknown, so it plans as if the opponent played no card.
 */
final readonly class BotStrategy
{
    /**
     * Its conflict cards being useless to it, the bot bids the least honor in the draw phase.
     */
    public const int HONOR_BID = 1;

    public const int MAX_HONOR_BID = 5;

    /**
     * At most this much additional fate on a character.
     */
    public const int MAX_ADDITIONAL_FATE = 3;

    /**
     * A character of at least this cost gets 1 additional fate when the bot can pay for it, so that it stays in play
     * after the fate phase.
     */
    public const int LASTING_CHARACTER_COST = 3;

    public function __construct(
        private AvailableActionsResolver $availableActionsResolver,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private ConflictService $conflictService,
        private BoardReader $boardReader,
        private RingEffectChooser $ringEffectChooser,
    ) {
    }

    /**
     * @return PlayerActionInterface|null the next message of the player, null when it has nothing to do now
     */
    public function nextAction(Game $game, Player $player): ?PlayerActionInterface
    {
        $available = $this->availableActionsResolver->resolve($game, $player);
        $gameId = $game->getId();
        $playerId = $player->getId();
        $playerState = $this->playerStateManager->getState($player);
        $duel = $this->gameStateManager->getState($game)->duel;

        if (in_array('choose_stronghold_province', $available, true) && $playerState->getStrongholdProvinceId() === null) {
            return new ChooseStrongholdProvince($gameId, $playerId, $this->chooseStrongholdProvince($player)->getId());
        }

        // RevealDynastyCard is accepted anytime: the step tells when it is due
        if ($game->getCurrentPlace() === 'reveal_facedown_dynasty_cards') {
            $card = $this->findFacedownDynastyCard($player);
            if ($card instanceof PhysicalCard) {
                return new RevealDynastyCard($gameId, $playerId, $card->getId());
            }
        }

        // RevealProvince is accepted anytime: the step tells when it is due
        if ($game->getCurrentPlace() === 'reveal_attacked_province') {
            $province = $this->findFacedownAttackedProvince($game, $player);
            if ($province instanceof PhysicalCard) {
                return new RevealProvince($gameId, $playerId, $province->getId());
            }
        }

        // the bot plays no interrupt nor reaction: what it resolves is the character it has played
        if (in_array('end_resolution', $available, true)) {
            return new EndResolution($gameId, $playerId);
        }

        if (in_array('collect_fate', $available, true)) {
            return new CollectFate($gameId, $playerId, $player->getStronghold()->getLogicalCard()->getFate() ?? 0);
        }

        if (in_array('bid_honor', $available, true)) {
            return new BidHonor($gameId, $playerId, $duel instanceof DuelState ? $this->duelHonorBid($game, $player, $duel) : self::HONOR_BID);
        }

        if (in_array('declare_duel_statistic', $available, true) && $duel instanceof DuelState) {
            return new DeclareDuelStatistic($gameId, $playerId, $this->boardReader->duelStatistic($this->duelCharacters($game, $duel, $playerId), $duel->type));
        }

        if (in_array('play_character_from_province', $available, true)) {
            $play = $this->playCharacterFromProvince($game, $player);
            if ($play instanceof PlayCharacterFromProvince) {
                return $play;
            }
        }

        if (in_array('declare_conflict', $available, true)) {
            $conflict = $this->declareConflict($game, $player);
            if ($conflict instanceof DeclareConflict) {
                return $conflict;
            }
        }

        if (in_array('pass_conflict', $available, true)) {
            return new PassConflict($gameId, $playerId);
        }

        if (in_array('declare_defenders', $available, true)) {
            $defenders = $this->chooseDefenders($game, $player);
            if ($defenders !== []) {
                return new DeclareDefenders($gameId, $playerId, array_map(fn (PhysicalCard $card): string => $card->getId(), $defenders));
            }
        }

        if (in_array('declare_no_defenders', $available, true)) {
            return new DeclareNoDefenders($gameId, $playerId);
        }

        if (in_array('declare_conflict_skill', $available, true)) {
            return new DeclareConflictSkill($gameId, $playerId, $this->conflictSkill($game, $player));
        }

        if (in_array('declare_glory_count', $available, true)) {
            return new DeclareGloryCount($gameId, $playerId, $this->boardReader->gloryCount($game, $player));
        }

        if (in_array('claim_imperial_favor', $available, true)) {
            return new ClaimImperialFavor($gameId, $playerId, ConflictType::Military);
        }

        if (in_array('pass_opportunity', $available, true)) {
            $opponent = $this->opponent($game, $player);
            if ($game->getCurrentPlace() === 'resolve_ring_effects' && $opponent instanceof Player) {
                $effect = $this->ringEffectChooser->nextAction($game, $player, $opponent);
                if ($effect instanceof PlayerActionInterface) {
                    return $effect;
                }
            }

            return new PassOpportunity($gameId, $playerId);
        }

        return null;
    }

    private function chooseStrongholdProvince(Player $player): PhysicalCard
    {
        $provinces = $player->getCardsByClass(ProvinceCard::class)->getValues();
        usort($provinces, fn (PhysicalCard $a, PhysicalCard $b): int => $a->getLogicalCard()->getId() <=> $b->getLogicalCard()->getId());

        return $provinces[0] ?? throw new \LogicException("The player {$player->getId()} has no province.");
    }

    /**
     * A face-down dynasty card in a regular province of the player (1.1).
     */
    private function findFacedownDynastyCard(Player $player): ?PhysicalCard
    {
        foreach ($player->getStacks(StackType::RegularProvince) as $province) {
            foreach ($province->getCards() as $card) {
                if ($card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEDOWN) {
                    return $card;
                }
            }
        }

        return null;
    }

    /**
     * A face-down province of the player attacked in the conflict (3.2).
     */
    private function findFacedownAttackedProvince(Game $game, Player $player): ?PhysicalCard
    {
        $attackedProvinceIds = $this->gameStateManager->getState($game)->currentConflict()->attackedProvinceIds;
        foreach ($player->getCardsByClass(ProvinceCard::class) as $province) {
            if (in_array($province->getId(), $attackedProvinceIds, true) && $province->getCurrentPlace() === ProvinceCard::STATE_FACEDOWN) {
                return $province;
            }
        }

        return null;
    }

    /**
     * The most expensive character it can afford (1.4), with the fate left once the characters it can still afford
     * after it are paid for, at least 1 on an expensive character.
     */
    private function playCharacterFromProvince(Game $game, Player $player): ?PlayCharacterFromProvince
    {
        $characters = $this->affordableCharacters($player);
        if ($characters === []) {
            return null;
        }

        $character = array_shift($characters);
        $cost = (int) $character->getLogicalCard()->getCost();
        $fateLeft = $this->playerStateManager->getState($player)->getFate() - $cost;
        $minimumFate = min($fateLeft, $cost >= self::LASTING_CHARACTER_COST ? 1 : 0);
        $fateLeft -= $minimumFate;
        foreach ($characters as $next) {
            $nextCost = (int) $next->getLogicalCard()->getCost();
            $sameUnique = $next->getLogicalCard()->getUniqueness() === true && $next->getLogicalCard()->getId() === $character->getLogicalCard()->getId();
            if ($nextCost <= $fateLeft && ! $sameUnique) {
                $fateLeft -= $nextCost;
            }
        }
        $fate = min($minimumFate + $fateLeft, self::MAX_ADDITIONAL_FATE);

        return new PlayCharacterFromProvince($game->getId(), $player->getId(), $character->getId(), $cost, $fate, $fate);
    }

    /**
     * The face-up characters in the provinces of the player that they can pay for, the most expensive first, but the
     * unique characters they already have in play.
     *
     * @return list<PhysicalCard>
     */
    private function affordableCharacters(Player $player): array
    {
        $fate = $this->playerStateManager->getState($player)->getFate();
        $inPlay = array_map(fn (PhysicalCard $card): string => $card->getLogicalCard()->getId(), $player->getCharactersInPlay());
        $characters = array_values($player->getCardsByCurrentPlace(DynastyCard::STATE_PROVINCE_FACEUP)->filter(
            fn (PhysicalCard $card): bool => $card->getLogicalCard()->getType() === Type::Character
                && $card->getLogicalCard()->getCost() !== null
                && $card->getLogicalCard()->getCost() <= $fate
                && ! ($card->getLogicalCard()->getUniqueness() === true && in_array($card->getLogicalCard()->getId(), $inPlay, true)),
        )->toArray());
        usort($characters, fn (PhysicalCard $a, PhysicalCard $b): int => $b->getLogicalCard()->getCost() <=> $a->getLogicalCard()->getCost() ?: $a->getId() <=> $b->getId());

        return $characters;
    }

    /**
     * The conflict it expects to win (3.2.1), as if the opponent defended with every ready character and played no
     * card: breaking a province first, then with the fewest attackers, so that the others stay home to defend.
     */
    private function declareConflict(Game $game, Player $player): ?DeclareConflict
    {
        $ring = $this->chooseRing($game);
        $opponent = $this->opponent($game, $player);
        if (! $ring instanceof Element || ! $opponent instanceof Player) {
            return null;
        }

        $best = null;
        $bestScore = null;
        foreach (ConflictType::cases() as $type) {
            if (in_array($type, $this->playerStateManager->getState($player)->getDeclaredConflictTypes(), true)) {
                continue;
            }

            $candidates = $this->boardReader->readyCharacters($player, $type);
            $defense = $this->boardReader->sideSkill($game, $this->boardReader->readyCharacters($opponent, $type), $type);
            foreach ($this->attackableProvinces($player, $opponent) as $province) {
                $strength = $this->boardReader->provinceStrength($province, $player);
                $attackers = $this->pickCharacters($game, $candidates, $type, max(1, $defense + $strength))
                    ?? $this->pickCharacters($game, $candidates, $type, max(1, $defense));
                if ($attackers === null) {
                    continue;
                }

                $breaks = $this->boardReader->sideSkill($game, $attackers, $type) - $defense >= $strength;
                $score = [$breaks ? 1 : 0, -count($attackers), -$strength];
                if ($bestScore === null || $score > $bestScore) {
                    $bestScore = $score;
                    $best = new DeclareConflict(
                        $game->getId(),
                        $player->getId(),
                        $ring,
                        $type,
                        $province->getId(),
                        array_map(fn (PhysicalCard $card): string => $card->getId(), $attackers),
                    );
                }
            }
        }

        return $best;
    }

    /**
     * The unclaimed ring with the most fate.
     */
    private function chooseRing(Game $game): ?Element
    {
        $state = $this->gameStateManager->getState($game);
        $rings = array_values(array_filter(Element::cases(), $state->isRingUnclaimed(...)));
        usort($rings, fn (Element $a, Element $b): int => $state->getRing($b)->fate <=> $state->getRing($a)->fate);

        return $rings[0] ?? null;
    }

    /**
     * @return list<ProvinceCard>
     */
    private function attackableProvinces(Player $player, Player $opponent): array
    {
        $provinces = [];
        foreach ($opponent->getCardsByClass(ProvinceCard::class) as $province) {
            if (! $province instanceof ProvinceCard) {
                continue;
            }

            try {
                $this->conflictService->assertAttackable($province, $player);
                $provinces[] = $province;
            } catch (ProvinceNotAttackableException) {
            }
        }
        usort($provinces, fn (ProvinceCard $a, ProvinceCard $b): int => $a->getId() <=> $b->getId());

        return $provinces;
    }

    /**
     * Defenders that win the conflict, else that keep the province from breaking (3.2.2). When neither is possible,
     * none, but its weakest character when it has no conflict left to declare: it would not attack with it, and it
     * keeps the conflict from being unopposed.
     *
     * @return list<PhysicalCard>
     */
    private function chooseDefenders(Game $game, Player $player): array
    {
        $conflict = $this->gameStateManager->getState($game)->currentConflict();
        $attackers = $this->cards($game, $conflict->attackerIds);
        $attack = $this->boardReader->sideSkill($game, $attackers, $conflict->type);
        $candidates = $this->boardReader->readyCharacters($player, $conflict->type);
        $province = $this->cards($game, [$conflict->provinceId])[0] ?? null;
        $strength = $province instanceof ProvinceCard ? $this->boardReader->provinceStrength($province, $player) : 0;

        $defenders = $this->pickCharacters($game, $candidates, $conflict->type, $attack + 1);
        if ($defenders === null && $attack >= $strength) {
            $defenders = $this->pickCharacters($game, $candidates, $conflict->type, $attack - $strength + 1);
        }

        if ($defenders === null && $attack > 0 && $candidates !== [] && ! $this->hasConflictLeft($player)) {
            $defenders = [$candidates[count($candidates) - 1]];
        }

        return $defenders ?? [];
    }

    private function hasConflictLeft(Player $player): bool
    {
        $state = $this->playerStateManager->getState($player);

        return $state->getConflictOpportunities() > 0 && count($state->getDeclaredConflictTypes()) < count(ConflictType::cases());
    }

    /**
     * The fewest characters, the strongest first, whose total skill reaches the target.
     *
     * @param list<PhysicalCard> $candidates the strongest first
     *
     * @return list<PhysicalCard>|null null when all of them do not reach it
     */
    private function pickCharacters(Game $game, array $candidates, ConflictType $type, int $target): ?array
    {
        $picked = [];
        foreach ($candidates as $candidate) {
            $picked[] = $candidate;
            if ($this->boardReader->sideSkill($game, $picked, $type) >= $target) {
                return $picked;
            }
        }

        return null;
    }

    /**
     * The skill of the side of the player in the conflict.
     */
    private function conflictSkill(Game $game, Player $player): int
    {
        $conflict = $this->gameStateManager->getState($game)->currentConflict();
        $participantIds = $conflict->attackingPlayerId === $player->getId() ? $conflict->attackerIds : $conflict->defenderIds;

        return $this->boardReader->sideSkill(
            $game,
            array_values(array_filter($player->getCharactersInPlay(), fn (PhysicalCard $character): bool => in_array($character->getId(), $participantIds, true))),
            $conflict->type,
        );
    }

    /**
     * The least honor when the bid cannot change the result, else enough to win against the lowest bid of the opponent.
     */
    private function duelHonorBid(Game $game, Player $player, DuelState $duel): int
    {
        $opponentId = $duel->challengerId === $player->getId() ? $duel->challengedId : $duel->challengerId;
        $statistic = $this->boardReader->duelStatistic($this->duelCharacters($game, $duel, $player->getId()), $duel->type);
        $opponentStatistic = $this->boardReader->duelStatistic($this->duelCharacters($game, $duel, $opponentId), $duel->type);
        if ($statistic + self::MAX_HONOR_BID <= $opponentStatistic + 1) {
            return 1;
        }

        return max(1, min(self::MAX_HONOR_BID, $opponentStatistic - $statistic + 2));
    }

    /**
     * @return list<PhysicalCard> the characters of the side of the player in the duel
     */
    private function duelCharacters(Game $game, DuelState $duel, string $playerId): array
    {
        return $this->cards($game, $duel->challengerId === $playerId ? $duel->challengerCardIds : $duel->challengedCardIds);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<PhysicalCard>
     */
    private function cards(Game $game, array $ids): array
    {
        return array_values($game->getPhysicalCards()->filter(fn (PhysicalCard $card): bool => in_array($card->getId(), $ids, true))->toArray());
    }

    private function opponent(Game $game, Player $player): ?Player
    {
        return $game->getPlayers()->findFirst(fn (int $key, Player $other): bool => $other !== $player);
    }
}
