<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'When a game last changed, and when each player last read it: whether a game is abandoned';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // the games in progress: when their last message was handled
        $this->addSql('UPDATE games SET updated_at = (SELECT MAX(e.created_at) FROM events e WHERE e.game_id = games.id)');
        $this->addSql('ALTER TABLE players ADD last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games DROP updated_at');
        $this->addSql('ALTER TABLE players DROP last_seen_at');
    }
}
