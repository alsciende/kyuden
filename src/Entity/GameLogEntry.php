<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GameLogEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A line of the log of a game: what changed in the game, for its players. Its text is a template whose placeholders
 * (`{player}`) are replaced by its params, encoded by App\GameLog\LogParam. A param may be known only to some players
 * (`visible_to`): the log is masked for each reader (App\View\GameLogViewFactory).
 *
 * Its version is the version of the game after the message whose handling produced it: a message produces any number
 * of entries, ordered by id.
 */
#[ORM\Entity(repositoryClass: GameLogEntryRepository::class)]
#[ORM\Table('game_log_entries')]
#[ORM\Index(columns: ['game_id', 'version'])]
class GameLogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue('SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column]
    private int $version;

    #[ORM\Column(type: Types::TEXT)]
    private string $template;

    /**
     * @var array<string, array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON, options: [
        'jsonb' => true,
    ])]
    private array $params;

    /**
     * @param array<string, array<string, mixed>> $params
     */
    public function __construct(Game $game, int $version, string $template, array $params)
    {
        $this->game = $game;
        $this->version = $version;
        $this->template = $template;
        $this->params = $params;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
