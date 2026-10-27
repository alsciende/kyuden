<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026234120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The invitations to a friendly game, the keys limited to one, the candidates waiting with one, the friendly games';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invitations (id VARCHAR(255) NOT NULL, kind VARCHAR(16) NOT NULL, code VARCHAR(16) DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, game_id VARCHAR(255) DEFAULT NULL, host_id VARCHAR NOT NULL, bot_id VARCHAR DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232710AE77153098 ON invitations (code)');
        $this->addSql('CREATE INDEX IDX_232710AE1FB8D185 ON invitations (host_id)');
        $this->addSql('CREATE INDEX IDX_232710AE92C1C487 ON invitations (bot_id)');
        $this->addSql('ALTER TABLE invitations ADD CONSTRAINT FK_232710AE1FB8D185 FOREIGN KEY (host_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invitations ADD CONSTRAINT FK_232710AE92C1C487 FOREIGN KEY (bot_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE access_tokens ADD invitation_id VARCHAR DEFAULT NULL');
        $this->addSql('ALTER TABLE access_tokens ADD CONSTRAINT FK_58D184BCA35D7AF0 FOREIGN KEY (invitation_id) REFERENCES invitations (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_58D184BCA35D7AF0 ON access_tokens (invitation_id)');
        $this->addSql('ALTER TABLE candidates ADD invitation_id VARCHAR DEFAULT NULL');
        $this->addSql('ALTER TABLE candidates ADD CONSTRAINT FK_6A77F80CA35D7AF0 FOREIGN KEY (invitation_id) REFERENCES invitations (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6A77F80CA35D7AF0 ON candidates (invitation_id)');
        $this->addSql('ALTER TABLE games ADD friendly BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT FK_232710AE1FB8D185');
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT FK_232710AE92C1C487');
        $this->addSql('DROP TABLE invitations');
        $this->addSql('ALTER TABLE access_tokens DROP CONSTRAINT FK_58D184BCA35D7AF0');
        $this->addSql('DROP INDEX IDX_58D184BCA35D7AF0');
        $this->addSql('ALTER TABLE access_tokens DROP invitation_id');
        $this->addSql('ALTER TABLE candidates DROP CONSTRAINT FK_6A77F80CA35D7AF0');
        $this->addSql('DROP INDEX IDX_6A77F80CA35D7AF0');
        $this->addSql('ALTER TABLE candidates DROP invitation_id');
        $this->addSql('ALTER TABLE games DROP friendly');
    }
}
