<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Mon;
use App\Repository\UnlockedMonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A mon a user has earned, to show at the top of their nobori (the ensō, which every player has, is never stored).
 * Written by NoboriCollection only.
 */
#[ORM\Entity(repositoryClass: UnlockedMonRepository::class)]
#[ORM\Table('unlocked_mons')]
class UnlockedMon
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Id]
    #[ORM\Column(length: 16, enumType: Mon::class)]
    private Mon $mon;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $unlockedAt;

    public function __construct(User $user, Mon $mon, \DateTimeImmutable $unlockedAt)
    {
        $this->user = $user;
        $this->mon = $mon;
        $this->unlockedAt = $unlockedAt;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getMon(): Mon
    {
        return $this->mon;
    }

    public function getUnlockedAt(): \DateTimeImmutable
    {
        return $this->unlockedAt;
    }
}
