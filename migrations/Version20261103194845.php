<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103194845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The spectators of the games';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE spectators (arrived_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, game_id VARCHAR NOT NULL, user_id VARCHAR NOT NULL, PRIMARY KEY (game_id, user_id))');
        $this->addSql('CREATE INDEX IDX_6B30FC91E48FD905 ON spectators (game_id)');
        $this->addSql('CREATE INDEX IDX_6B30FC91A76ED395 ON spectators (user_id)');
        $this->addSql('ALTER TABLE spectators ADD CONSTRAINT FK_6B30FC91E48FD905 FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE spectators ADD CONSTRAINT FK_6B30FC91A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE spectators DROP CONSTRAINT FK_6B30FC91E48FD905');
        $this->addSql('ALTER TABLE spectators DROP CONSTRAINT FK_6B30FC91A76ED395');
        $this->addSql('DROP TABLE spectators');
    }
}
