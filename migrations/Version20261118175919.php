<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118175919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The matches of the tournaments, their rounds and their winners';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE tournament_matches_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE tournament_matches (id INT NOT NULL, round INT NOT NULL, table_number INT NOT NULL, outcome VARCHAR(16) DEFAULT NULL, victory_condition VARCHAR(32) DEFAULT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, tournament_id VARCHAR NOT NULL, first_id INT NOT NULL, second_id INT DEFAULT NULL, game_id VARCHAR DEFAULT NULL, winner_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D29EEB033D1A3E7C5EEEA34C8421A97 ON tournament_matches (tournament_id, round, table_number)');
        $this->addSql('CREATE INDEX IDX_6D29EEB033D1A3E7 ON tournament_matches (tournament_id)');
        $this->addSql('CREATE INDEX IDX_6D29EEB0E84D625F ON tournament_matches (first_id)');
        $this->addSql('CREATE INDEX IDX_6D29EEB0FF961BCC ON tournament_matches (second_id)');
        $this->addSql('CREATE INDEX IDX_6D29EEB0E48FD905 ON tournament_matches (game_id)');
        $this->addSql('CREATE INDEX IDX_6D29EEB05DFCD4B8 ON tournament_matches (winner_id)');
        $this->addSql('ALTER TABLE tournament_matches ADD CONSTRAINT FK_6D29EEB033D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournament_matches ADD CONSTRAINT FK_6D29EEB0E84D625F FOREIGN KEY (first_id) REFERENCES tournament_entries (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournament_matches ADD CONSTRAINT FK_6D29EEB0FF961BCC FOREIGN KEY (second_id) REFERENCES tournament_entries (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournament_matches ADD CONSTRAINT FK_6D29EEB0E48FD905 FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournament_matches ADD CONSTRAINT FK_6D29EEB05DFCD4B8 FOREIGN KEY (winner_id) REFERENCES tournament_entries (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE candidates ADD match_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE candidates ADD CONSTRAINT FK_6A77F80C2ABEACD6 FOREIGN KEY (match_id) REFERENCES tournament_matches (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6A77F80C2ABEACD6 ON candidates (match_id)');
        $this->addSql('ALTER TABLE tournament_entries ADD eliminated BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE tournaments ADD size INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tournaments ADD current_round INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tournaments ADD round_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE tournaments ADD winner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tournaments ADD CONSTRAINT FK_E4BCFAC35DFCD4B8 FOREIGN KEY (winner_id) REFERENCES tournament_entries (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E4BCFAC35DFCD4B8 ON tournaments (winner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE tournament_matches_id_seq CASCADE');
        $this->addSql('ALTER TABLE tournament_matches DROP CONSTRAINT FK_6D29EEB033D1A3E7');
        $this->addSql('ALTER TABLE tournament_matches DROP CONSTRAINT FK_6D29EEB0E84D625F');
        $this->addSql('ALTER TABLE tournament_matches DROP CONSTRAINT FK_6D29EEB0FF961BCC');
        $this->addSql('ALTER TABLE tournament_matches DROP CONSTRAINT FK_6D29EEB0E48FD905');
        $this->addSql('ALTER TABLE tournament_matches DROP CONSTRAINT FK_6D29EEB05DFCD4B8');
        $this->addSql('DROP TABLE tournament_matches');
        $this->addSql('ALTER TABLE candidates DROP CONSTRAINT FK_6A77F80C2ABEACD6');
        $this->addSql('DROP INDEX IDX_6A77F80C2ABEACD6');
        $this->addSql('ALTER TABLE candidates DROP match_id');
        $this->addSql('ALTER TABLE tournament_entries DROP eliminated');
        $this->addSql('ALTER TABLE tournaments DROP CONSTRAINT FK_E4BCFAC35DFCD4B8');
        $this->addSql('DROP INDEX IDX_E4BCFAC35DFCD4B8');
        $this->addSql('ALTER TABLE tournaments DROP size');
        $this->addSql('ALTER TABLE tournaments DROP current_round');
        $this->addSql('ALTER TABLE tournaments DROP round_started_at');
        $this->addSql('ALTER TABLE tournaments DROP winner_id');
    }
}
