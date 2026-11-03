<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpectatorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An account watching a game it may not play: it reads the game as a reader who is not a player (only what is public).
 * It watches while it reads the game: once it has not read it for SpectatorRegistry::TIMEOUT, or once it leaves, it no
 * longer does. Written by SpectatorRegistry only, in SQL (two reads of the same spectator at once).
 */
#[ORM\Entity(repositoryClass: SpectatorRepository::class)]
#[ORM\Table('spectators')]
class Spectator
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** when it began to watch: the spectators are listed in their order of arrival */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $arrivedAt;

    /** when it last read the game */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct(Game $game, User $user, \DateTimeImmutable $now)
    {
        $this->game = $game;
        $this->user = $user;
        $this->arrivedAt = $now;
        $this->lastSeenAt = $now;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getArrivedAt(): \DateTimeImmutable
    {
        return $this->arrivedAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }
}
