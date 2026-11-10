<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110200124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'When an access token was last used, from which a session of an account expires: the tokens issued before count as used now';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE access_tokens ADD last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE access_tokens SET last_used_at = LOCALTIMESTAMP(0)');
        $this->addSql('ALTER TABLE access_tokens ALTER last_used_at SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE access_tokens DROP last_used_at');
    }
}
