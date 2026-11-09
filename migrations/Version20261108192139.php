<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108192139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The replays of the games that have ended: the game a copy replays and the user who follows it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games ADD replay_of_id VARCHAR DEFAULT NULL');
        $this->addSql('ALTER TABLE games ADD replay_user_id VARCHAR DEFAULT NULL');
        $this->addSql('ALTER TABLE games ADD CONSTRAINT FK_FF232B31DA7FCFC8 FOREIGN KEY (replay_of_id) REFERENCES games (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE games ADD CONSTRAINT FK_FF232B3163C31528 FOREIGN KEY (replay_user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX replay_of_user ON games (replay_of_id, replay_user_id)');
        $this->addSql('CREATE INDEX IDX_FF232B31DA7FCFC8 ON games (replay_of_id)');
        $this->addSql('CREATE INDEX IDX_FF232B3163C31528 ON games (replay_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games DROP CONSTRAINT FK_FF232B31DA7FCFC8');
        $this->addSql('ALTER TABLE games DROP CONSTRAINT FK_FF232B3163C31528');
        $this->addSql('DROP INDEX replay_of_user');
        $this->addSql('DROP INDEX IDX_FF232B31DA7FCFC8');
        $this->addSql('DROP INDEX IDX_FF232B3163C31528');
        $this->addSql('ALTER TABLE games DROP replay_of_id');
        $this->addSql('ALTER TABLE games DROP replay_user_id');
    }
}
