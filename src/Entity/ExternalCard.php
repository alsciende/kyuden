<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CardSource;
use App\Repository\ExternalCardRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links the id of a card in an external source (e.g. EmeraldDB) to one of the cards of the engine, to import the
 * decks built there. An external id names a single card of its source; a card can have several external ids.
 */
#[ORM\Entity(repositoryClass: ExternalCardRepository::class)]
#[ORM\Table('external_cards')]
#[ORM\UniqueConstraint(columns: ['source', 'external_id'])]
class ExternalCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue('SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: CardSource::class)]
    private CardSource $source;

    #[ORM\Column(length: 100)]
    private string $externalId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LogicalCard $card;

    public function __construct(CardSource $source, string $externalId, LogicalCard $card)
    {
        $this->source = $source;
        $this->externalId = $externalId;
        $this->card = $card;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): CardSource
    {
        return $this->source;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getCard(): LogicalCard
    {
        return $this->card;
    }

    public function setCard(LogicalCard $card): void
    {
        $this->card = $card;
    }
}
