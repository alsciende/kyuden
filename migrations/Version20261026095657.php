<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026095657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The identities at providers (OAuth2) linked to the accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE linked_accounts_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE linked_accounts (id INT NOT NULL, provider VARCHAR(16) NOT NULL, subject VARCHAR(255) NOT NULL, linked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id VARCHAR NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX linked_account_identity ON linked_accounts (provider, subject)');
        $this->addSql('CREATE UNIQUE INDEX linked_account_user_provider ON linked_accounts (user_id, provider)');
        $this->addSql('CREATE INDEX IDX_A4C8D073A76ED395 ON linked_accounts (user_id)');
        $this->addSql('ALTER TABLE linked_accounts ADD CONSTRAINT FK_A4C8D073A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE linked_accounts DROP CONSTRAINT FK_A4C8D073A76ED395');
        $this->addSql('DROP TABLE linked_accounts');
        $this->addSql('DROP SEQUENCE linked_accounts_id_seq CASCADE');
    }
}
