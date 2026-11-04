<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104135425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The timings of the abilities of the cards, and whether they are granted to the card attached to, move to the card scripts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logical_cards DROP ability_timings');
        $this->addSql('ALTER TABLE logical_cards DROP ability_granted_to_attached');
    }

    public function down(Schema $schema): void
    {
        // filled again by app:cards:import from the fixtures of the version migrated back to
        $this->addSql("ALTER TABLE logical_cards ADD ability_timings text[] DEFAULT '{}' NOT NULL");
        $this->addSql('ALTER TABLE logical_cards ADD ability_granted_to_attached BOOLEAN DEFAULT false NOT NULL');
    }
}
