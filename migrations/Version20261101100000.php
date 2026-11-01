<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Deck;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The content hash of the decks (computed here for the decks already there) and their deletion by their owner';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE decks ADD content_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE decks ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // the hash of Deck, so that the decks already there match the decks imported
        foreach ($this->connection->fetchAllAssociative('SELECT id, cards FROM decks') as $deck) {
            /** @var array<string, int> $cards */
            $cards = json_decode((string) $deck['cards'], true, flags: \JSON_THROW_ON_ERROR);
            $this->addSql('UPDATE decks SET content_hash = :hash WHERE id = :id', ['hash' => Deck::hashCards($cards), 'id' => $deck['id']]);
        }
        $this->addSql('ALTER TABLE decks ALTER content_hash SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE decks DROP content_hash');
        $this->addSql('ALTER TABLE decks DROP deleted_at');
    }
}
