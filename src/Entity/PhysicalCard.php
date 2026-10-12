<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\CardTypes\RoleCard;
use App\Entity\CardTypes\StrongholdCard;
use App\Enum\Orientation;
use App\Enum\Side;
use App\Enum\StackType;
use App\Enum\Type;
use App\Repository\CardRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One of the stateful, physical cards in a deck *during a game*.
 *
 * The workflow place of a card is not stored: it is computed from the type of its stack and its orientation.
 */
#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\MappedSuperclass]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'side', type: 'string', length: 10)]
#[ORM\DiscriminatorMap(self::DISCRIMINATOR_MAP)]
#[ORM\Table('physical_cards')]
abstract class PhysicalCard
{
    public const array DISCRIMINATOR_MAP = [
        Side::Conflict->value => ConflictCard::class,
        Side::Dynasty->value => DynastyCard::class,
        Side::Province->value => ProvinceCard::class,
        Side::Role->value => RoleCard::class,
        Side::Stronghold->value => StrongholdCard::class,
    ];

    #[ORM\Id]
    #[ORM\Column]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'physicalCards')]
    #[ORM\JoinColumn(nullable: false)]
    private LogicalCard $logicalCard;

    #[ORM\Column(length: 20, enumType: Orientation::class)]
    private Orientation $orientation;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    /**
     * @var array<string,mixed>
     */
    #[ORM\Column]
    protected array $state = [];

    #[ORM\ManyToOne(inversedBy: 'physicalCards')]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\ManyToOne(inversedBy: 'physicalCards')]
    #[ORM\JoinColumn(nullable: false)]
    private Player $player;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private Stack $stack;

    public function __construct(string $id, LogicalCard $logicalCard, Stack $stack, ?Orientation $orientation = null)
    {
        $this->id = $id;
        $this->logicalCard = $logicalCard;
        $this->stack = $stack;
        $this->orientation = $orientation ?? static::getInitialOrientation();
    }

    /**
     * Orientation of a card of this type when the game starts. Later on, the transitions of the workflow
     * set the orientation of the card.
     */
    abstract public static function getInitialOrientation(): Orientation;

    /**
     * Workflow place of a card of this type in a stack of this type with this orientation,
     * or null if the card cannot be in that situation.
     */
    abstract public static function getPlace(StackType $stackType, Orientation $orientation): ?string;

    public function getId(): string
    {
        return $this->id;
    }

    public function getLogicalCard(): LogicalCard
    {
        return $this->logicalCard;
    }

    /**
     * @throws \LogicException if the card is in a stack or orientation it cannot have
     */
    public function getCurrentPlace(): string
    {
        $stackType = $this->stack->getType();

        return static::getPlace($stackType, $this->orientation) ?? throw new \LogicException(sprintf('Card %s (%s) cannot be %s in stack %s.', $this->title ?? $this->id, static::class, $this->orientation->value, $stackType->name));
    }

    /**
     * A character enters play in a stack of its own; any other card (e.g. an attachment) joins the stack
     * of the card or game element it is put on.
     */
    public function entersPlayInNewStack(): bool
    {
        return $this->logicalCard->getType() === Type::Character;
    }

    public function getOrientation(): Orientation
    {
        return $this->orientation;
    }

    public function setOrientation(Orientation $orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    /**
     * @return array<string,mixed>
     */
    public function getState(): array
    {
        return $this->state;
    }

    /**
     * @return $this
     */
    public function setState(string $key, mixed $value): static
    {
        $this->state[$key] = $value;

        return $this;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): static
    {
        $this->player = $player;

        return $this;
    }

    public function getStack(): Stack
    {
        return $this->stack;
    }

    public function setStack(Stack $stack): static
    {
        $this->stack = $stack;

        return $this;
    }

    /**
     * @return list<Type>
     */
    abstract public function getAllowedTypes(): array;
}
