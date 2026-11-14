<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113230655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The nobori of the users (a mon on a pattern: the ensō without pattern to start with), and the mons and patterns they have earned';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE unlocked_mons (mon VARCHAR(16) NOT NULL, unlocked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id VARCHAR NOT NULL, PRIMARY KEY (user_id, mon))');
        $this->addSql('CREATE INDEX IDX_B39CDFF5A76ED395 ON unlocked_mons (user_id)');
        $this->addSql('CREATE TABLE unlocked_patterns (pattern VARCHAR(16) NOT NULL, unlocked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id VARCHAR NOT NULL, PRIMARY KEY (user_id, pattern))');
        $this->addSql('CREATE INDEX IDX_57A6134EA76ED395 ON unlocked_patterns (user_id)');
        $this->addSql('ALTER TABLE unlocked_mons ADD CONSTRAINT FK_B39CDFF5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE unlocked_patterns ADD CONSTRAINT FK_57A6134EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE users ADD banner_mon VARCHAR(16) DEFAULT \'enso\' NOT NULL');
        $this->addSql('ALTER TABLE users ADD banner_pattern VARCHAR(16) DEFAULT \'none\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unlocked_mons DROP CONSTRAINT FK_B39CDFF5A76ED395');
        $this->addSql('ALTER TABLE unlocked_patterns DROP CONSTRAINT FK_57A6134EA76ED395');
        $this->addSql('DROP TABLE unlocked_mons');
        $this->addSql('DROP TABLE unlocked_patterns');
        $this->addSql('ALTER TABLE users DROP banner_mon');
        $this->addSql('ALTER TABLE users DROP banner_pattern');
    }
}
