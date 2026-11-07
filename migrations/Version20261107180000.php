<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The card the cards of a stack are under (PlaceCardUnder)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stack ADD host_id VARCHAR DEFAULT NULL');
        $this->addSql('ALTER TABLE stack ADD CONSTRAINT FK_41A87B6A1FB8D185 FOREIGN KEY (host_id) REFERENCES physical_cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_41A87B6A1FB8D185 ON stack (host_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stack DROP CONSTRAINT FK_41A87B6A1FB8D185');
        $this->addSql('DROP INDEX IDX_41A87B6A1FB8D185');
        $this->addSql('ALTER TABLE stack DROP host_id');
    }
}
