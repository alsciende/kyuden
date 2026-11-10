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
use App\State\Model\StatefulInterface;
use Doctrine\DBAL\Types\Types;
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
abstract class PhysicalCard implements StatefulInterface
{
    /**
     * The places of a card in play, in the conflict and dynasty card workflows: a character or an attachment (`in_play`),
     * a character attached to another one as an attachment (`attached`).
     */
    public const array IN_PLAY_PLACES = [DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED];

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
     * What is not a physical position of the card (fate tokens…), decoded by CardStateManager.
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $state = '{}';

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
     * Whether one of the places (the places a transition leads to) is a place in play.
     *
     * @param array<mixed> $places
     */
    public static function hasInPlayPlace(array $places): bool
    {
        return array_any($places, fn (mixed $place): bool => in_array($place, self::IN_PLAY_PLACES, true));
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

    #[\Override]
    public function getId(): string
    {
        return $this->id;
    }

    public function getLogicalCard(): LogicalCard
    {
        return $this->logicalCard;
    }

    /**
     * Debugging only (app:card:swap): a card never changes what it is printed as during a game.
     */
    public function setLogicalCard(LogicalCard $logicalCard): static
    {
        $this->logicalCard = $logicalCard;

        return $this;
    }

    /**
     * The place given by its stack and orientation (getPlace()), except for a character attached to another one as an
     * attachment (PlayCharacterAsAttachment), which is `attached` rather than `in_play`.
     *
     * @throws \LogicException if the card is in a stack or orientation it cannot have
     */
    public function getCurrentPlace(): string
    {
        $stackType = $this->stack->getType();
        $place = static::getPlace($stackType, $this->orientation) ?? throw new \LogicException(sprintf('Card %s (%s) cannot be %s in stack %s.', $this->title ?? $this->id, static::class, $this->orientation->value, $stackType->name));

        return $place === DynastyCard::STATE_IN_PLAY && $this->isAttachedCharacter() ? DynastyCard::STATE_ATTACHED : $place;
    }

    /**
     * Whether the card is a character in play: the first card of a stack in play, whatever its printed type (an
     * attachment turned into a character: Togashi Hoshi). The cards above it are attached to it.
     */
    public function isCharacterInPlay(): bool
    {
        return $this->stack->getType() === StackType::InPlay && $this->stack->getOrderedCards()[0] === $this;
    }

    /**
     * The character in play the card is attached to (an attachment, or a card played as one), null for a card that is
     * not attached to a character (a character in play, an attachment on a province, a card out of play).
     */
    public function getAttachedCharacter(): ?self
    {
        if ($this->stack->getType() !== StackType::InPlay) {
            return null;
        }

        $character = $this->stack->getOrderedCards()[0] ?? null;

        return $character === $this ? null : $character;
    }

    /**
     * Whether the card is printed as a character and in play as an attachment, attached to another character.
     */
    public function isAttachedCharacter(): bool
    {
        return $this->logicalCard->getType() === Type::Character && $this->getAttachedCharacter() instanceof self;
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

    #[\Override]
    public function getState(): string
    {
        return $this->state;
    }

    #[\Override]
    public function setState(string $state): void
    {
        $this->state = $state;
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
