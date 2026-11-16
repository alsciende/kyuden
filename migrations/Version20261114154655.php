<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114154655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The trophies the users have earned, with when and in which game';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE earned_trophies (trophy VARCHAR(32) NOT NULL, earned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id VARCHAR NOT NULL, game_id VARCHAR DEFAULT NULL, PRIMARY KEY (user_id, trophy))');
        $this->addSql('CREATE INDEX IDX_C39863D1A76ED395 ON earned_trophies (user_id)');
        $this->addSql('CREATE INDEX IDX_C39863D1E48FD905 ON earned_trophies (game_id)');
        $this->addSql('ALTER TABLE earned_trophies ADD CONSTRAINT FK_C39863D1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE earned_trophies ADD CONSTRAINT FK_C39863D1E48FD905 FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE earned_trophies DROP CONSTRAINT FK_C39863D1A76ED395');
        $this->addSql('ALTER TABLE earned_trophies DROP CONSTRAINT FK_C39863D1E48FD905');
        $this->addSql('DROP TABLE earned_trophies');
    }
}
