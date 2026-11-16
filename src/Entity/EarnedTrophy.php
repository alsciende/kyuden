<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Trophy;
use App\Repository\EarnedTrophyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A trophy a user has earned, once, with when and in which game (none for a trophy that no game gives). Written by
 * TrophyCase only.
 */
#[ORM\Entity(repositoryClass: EarnedTrophyRepository::class)]
#[ORM\Table('earned_trophies')]
class EarnedTrophy
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Id]
    #[ORM\Column(length: 32, enumType: Trophy::class)]
    private Trophy $trophy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $earnedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Game $game;

    public function __construct(User $user, Trophy $trophy, \DateTimeImmutable $earnedAt, ?Game $game)
    {
        $this->user = $user;
        $this->trophy = $trophy;
        $this->earnedAt = $earnedAt;
        $this->game = $game;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTrophy(): Trophy
    {
        return $this->trophy;
    }

    public function getEarnedAt(): \DateTimeImmutable
    {
        return $this->earnedAt;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }
}
