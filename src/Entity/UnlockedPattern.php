<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BannerPattern;
use App\Repository\UnlockedPatternRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A pattern a user has earned for their nobori (none, which every player has, is never stored).
 * Written by NoboriCollection only.
 */
#[ORM\Entity(repositoryClass: UnlockedPatternRepository::class)]
#[ORM\Table('unlocked_patterns')]
class UnlockedPattern
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Id]
    #[ORM\Column(length: 16, enumType: BannerPattern::class)]
    private BannerPattern $pattern;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $unlockedAt;

    public function __construct(User $user, BannerPattern $pattern, \DateTimeImmutable $unlockedAt)
    {
        $this->user = $user;
        $this->pattern = $pattern;
        $this->unlockedAt = $unlockedAt;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPattern(): BannerPattern
    {
        return $this->pattern;
    }

    public function getUnlockedAt(): \DateTimeImmutable
    {
        return $this->unlockedAt;
    }
}
