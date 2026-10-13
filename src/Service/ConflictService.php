<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Enum\Type;
use App\Exception\Rules\CannotParticipateException;
use App\Exception\Rules\NotInPlayException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\ProvinceNotAttackableException;
use App\Exception\Rules\WrongCardTypeException;
use App\State\Manager\PlayerStateManager;

/**
 * Rules about what can be attacked and who can participate in a conflict. The controller of a card is its owner,
 * since control is not modeled.
 */
readonly class ConflictService
{
    /**
     * Stronghold format: the stronghold province can be attacked once that many other provinces of its owner are broken.
     */
    public const int BROKEN_PROVINCES_TO_ATTACK_STRONGHOLD = 3;

    public function __construct(
        private PlayerStateManager $playerStateManager,
    ) {
    }

    /**
     * An unbroken province of an opponent; the stronghold province only once enough other provinces are broken.
     *
     * @return Player the defending player
     */
    public function assertAttackable(PhysicalCard $province, Player $attacker): Player
    {
        if (! $province instanceof ProvinceCard) {
            throw new WrongCardTypeException($province, Type::Province);
        }

        $defender = $province->getPlayer();
        if ($defender === $attacker) {
            throw new ProvinceNotAttackableException($province, 'it belongs to the attacking player');
        }

        if ($province->getCurrentPlace() === ProvinceCard::STATE_BROKEN) {
            throw new ProvinceNotAttackableException($province, 'it is broken');
        }

        if ($this->isStrongholdProvince($province) && $this->countBrokenProvinces($defender) < self::BROKEN_PROVINCES_TO_ATTACK_STRONGHOLD) {
            throw new ProvinceNotAttackableException($province, sprintf('fewer than %d other provinces are broken', self::BROKEN_PROVINCES_TO_ATTACK_STRONGHOLD));
        }

        return $defender;
    }

    /**
     * A ready character in play, controlled by the player.
     */
    public function assertCanParticipate(PhysicalCard $card, Player $player): void
    {
        $this->assertCharacterInPlay($card, $player);
        if ($card->getOrientation() !== Orientation::FaceUp) {
            throw new CannotParticipateException($card, 'it is bowed');
        }
    }

    /**
     * A character in play, controlled by the player if one is given.
     */
    public function assertCharacterInPlay(PhysicalCard $card, ?Player $player = null): void
    {
        if ($player instanceof Player && $card->getPlayer() !== $player) {
            throw new NotOwnedCardException($card, $player);
        }

        if ($card->getLogicalCard()->getType() !== Type::Character) {
            throw new WrongCardTypeException($card, Type::Character);
        }

        if ($card->getStack()->getType() !== StackType::InPlay) {
            throw new NotInPlayException($card);
        }
    }

    public function isStrongholdProvince(ProvinceCard $province): bool
    {
        return $this->playerStateManager->getState($province->getPlayer())->getStrongholdProvinceId() === $province->getId();
    }

    /**
     * Broken provinces of the player, the stronghold province excluded.
     */
    public function countBrokenProvinces(Player $player): int
    {
        return $player->getCardsByClass(ProvinceCard::class)->filter(
            fn (PhysicalCard $province): bool => $province instanceof ProvinceCard
                && ! $this->isStrongholdProvince($province)
                && $province->getCurrentPlace() === ProvinceCard::STATE_BROKEN,
        )->count();
    }
}
