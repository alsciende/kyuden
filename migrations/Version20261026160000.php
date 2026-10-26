<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The date an account was deleted (anonymized)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP deleted_at');
    }
}
