<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118154412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The tournaments and their registrations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE tournament_entries_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE tournament_entries (id INT NOT NULL, registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tournament_id VARCHAR NOT NULL, user_id VARCHAR NOT NULL, deck_id VARCHAR NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_46F748CF33D1A3E7A76ED395 ON tournament_entries (tournament_id, user_id)');
        $this->addSql('CREATE INDEX IDX_46F748CF33D1A3E7 ON tournament_entries (tournament_id)');
        $this->addSql('CREATE INDEX IDX_46F748CFA76ED395 ON tournament_entries (user_id)');
        $this->addSql('CREATE INDEX IDX_46F748CF111948DC ON tournament_entries (deck_id)');
        $this->addSql('CREATE TABLE tournaments (id VARCHAR(255) NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, format VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, seed VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, organizer_id VARCHAR NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E4BCFAC355A0507C ON tournaments (starts_at)');
        $this->addSql('CREATE INDEX IDX_E4BCFAC3876C4DDA ON tournaments (organizer_id)');
        $this->addSql('ALTER TABLE tournament_entries ADD CONSTRAINT FK_46F748CF33D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournament_entries ADD CONSTRAINT FK_46F748CFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournament_entries ADD CONSTRAINT FK_46F748CF111948DC FOREIGN KEY (deck_id) REFERENCES decks (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournaments ADD CONSTRAINT FK_E4BCFAC3876C4DDA FOREIGN KEY (organizer_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE tournament_entries_id_seq CASCADE');
        $this->addSql('ALTER TABLE tournament_entries DROP CONSTRAINT FK_46F748CF33D1A3E7');
        $this->addSql('ALTER TABLE tournament_entries DROP CONSTRAINT FK_46F748CFA76ED395');
        $this->addSql('ALTER TABLE tournament_entries DROP CONSTRAINT FK_46F748CF111948DC');
        $this->addSql('ALTER TABLE tournaments DROP CONSTRAINT FK_E4BCFAC3876C4DDA');
        $this->addSql('DROP TABLE tournament_entries');
        $this->addSql('DROP TABLE tournaments');
    }
}
