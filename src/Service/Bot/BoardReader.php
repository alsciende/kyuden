<?php

declare(strict_types=1);

namespace App\Service\Bot;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\Enum\DuelType;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Enum\Type;
use App\Service\ConflictService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;

/**
 * The values a bot reads on the board, computed from the printed values of the cards like the web client prefills
 * them (front/src/lib/board.ts): the engine does not compute skills, glory nor strengths. Only what the bot can see is
 * read: the strength of a face-down province of an opponent is assumed.
 */
final readonly class BoardReader
{
    /**
     * The most common printed strength of the provinces.
     */
    public const int ASSUMED_PROVINCE_STRENGTH = 4;

    public function __construct(
        private GameStateManager $gameStateManager,
        private CardStateManager $cardStateManager,
        private ConflictService $conflictService,
    ) {
    }

    /**
     * The current skill of a character: the one recorded by the players, else the printed skill with the bonuses of its
     * attachments and its personal honor, at least 0; null when it has no skill of that type (a dash).
     */
    public function skill(PhysicalCard $character, ConflictType $type): ?int
    {
        $state = $this->cardStateManager->getState($character);
        $recorded = $type === ConflictType::Military ? $state->getMilitarySkill() : $state->getPoliticalSkill();
        if ($recorded !== null) {
            return $recorded;
        }

        $printed = $this->printedSkill($character, $type);
        if ($printed === null) {
            return null;
        }

        $skill = (int) $printed;
        foreach ($character->getStack()->getCards() as $attachment) {
            if ($attachment->getLogicalCard()->getType() === Type::Attachment) {
                $skill += (int) ($type === ConflictType::Military ? $attachment->getLogicalCard()->getMilitaryBonus() : $attachment->getLogicalCard()->getPoliticalBonus());
            }
        }

        $glory = $character->getLogicalCard()->getGlory() ?? 0;
        $skill += match ($state->getPersonalHonor()) {
            PersonalHonor::Honored => $glory,
            PersonalHonor::Dishonored => -$glory,
            PersonalHonor::Ordinary => 0,
        };

        return max(0, $skill);
    }

    /**
     * The skill of a character, 0 for a dash.
     */
    public function skillOrZero(PhysicalCard $character, ConflictType $type): int
    {
        return $this->skill($character, $type) ?? 0;
    }

    /**
     * The total skill of the ready characters among the given ones, with the Imperial Favor of their player.
     *
     * @param list<PhysicalCard> $characters characters of the same player
     */
    public function sideSkill(Game $game, array $characters, ConflictType $type): int
    {
        $skill = 0;
        foreach ($characters as $character) {
            if ($character->getOrientation() !== Orientation::Sideways) {
                $skill += $this->skillOrZero($character, $type);
            }
        }

        return $skill + ($characters !== [] && $this->hasImperialFavor($game, $characters[0]->getPlayer(), $type) ? 1 : 0);
    }

    public function hasImperialFavor(Game $game, Player $player, ConflictType $type): bool
    {
        $state = $this->gameStateManager->getState($game);

        return $state->imperialFavorHolderId === $player->getId() && $state->imperialFavorSide === $type;
    }

    /**
     * The dueling statistic of the given characters.
     *
     * @param list<PhysicalCard> $characters
     */
    public function duelStatistic(array $characters, DuelType $type): int
    {
        $statistic = 0;
        foreach ($characters as $character) {
            $statistic += match ($type) {
                DuelType::Military => $this->skillOrZero($character, ConflictType::Military),
                DuelType::Political => $this->skillOrZero($character, ConflictType::Political),
                DuelType::Glory => $character->getLogicalCard()->getGlory() ?? 0,
            };
        }

        return $statistic;
    }

    /**
     * The glory count of a player: the printed glory of their ready characters, plus one for each ring they claimed.
     */
    public function gloryCount(Game $game, Player $player): int
    {
        $glory = 0;
        foreach ($player->getCharactersInPlay() as $character) {
            if ($character->getOrientation() !== Orientation::Sideways) {
                $glory += $character->getLogicalCard()->getGlory() ?? 0;
            }
        }

        return $glory + $this->gameStateManager->getState($game)->countClaimedRings($player);
    }

    /**
     * The strength of a province as the viewer sees it: printed, plus the bonus of the stronghold on the stronghold
     * province; assumed when it is face down and belongs to another player.
     */
    public function provinceStrength(ProvinceCard $province, Player $viewer): int
    {
        if ($province->getPlayer() !== $viewer && $province->getCurrentPlace() === ProvinceCard::STATE_FACEDOWN) {
            return self::ASSUMED_PROVINCE_STRENGTH;
        }

        $strength = (int) $province->getLogicalCard()->getStrength();
        if ($this->conflictService->isStrongholdProvince($province)) {
            $strength += (int) $province->getPlayer()->getStronghold()->getLogicalCard()->getStrengthBonus();
        }

        return $strength;
    }

    /**
     * The ready characters of the player that have a skill of that type, the strongest first.
     *
     * @return list<PhysicalCard>
     */
    public function readyCharacters(Player $player, ConflictType $type): array
    {
        $characters = array_values(array_filter(
            $player->getCharactersInPlay(),
            fn (PhysicalCard $character): bool => $character->getOrientation() === Orientation::FaceUp && $this->skill($character, $type) !== null,
        ));
        usort($characters, fn (PhysicalCard $a, PhysicalCard $b): int => $this->skillOrZero($b, $type) <=> $this->skillOrZero($a, $type) ?: $a->getId() <=> $b->getId());

        return $characters;
    }

    private function printedSkill(PhysicalCard $character, ConflictType $type): ?string
    {
        $printed = $type === ConflictType::Military ? $character->getLogicalCard()->getMilitary() : $character->getLogicalCard()->getPolitical();

        return $printed === null || $printed === '-' ? null : $printed;
    }
}
