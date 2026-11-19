<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written by hand: the table is renamed, never dropped and created again, since its rows are the replays of the games.
 */
final class Version20261119100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The events of the games become their action records (Entity/ActionRecord)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events RENAME TO action_records');
        $this->addSql('ALTER SEQUENCE events_id_seq RENAME TO action_records_id_seq');
        $this->addSql('ALTER INDEX events_pkey RENAME TO action_records_pkey');
        $this->addSql('ALTER INDEX IDX_5387574AE48FD905 RENAME TO IDX_463FD0A7E48FD905');
        $this->addSql('ALTER INDEX UNIQ_5387574AE48FD905BF1CD3C3 RENAME TO UNIQ_463FD0A7E48FD905BF1CD3C3');
        $this->addSql('ALTER TABLE action_records RENAME CONSTRAINT FK_5387574AE48FD905 TO FK_463FD0A7E48FD905');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_records RENAME CONSTRAINT FK_463FD0A7E48FD905 TO FK_5387574AE48FD905');
        $this->addSql('ALTER INDEX UNIQ_463FD0A7E48FD905BF1CD3C3 RENAME TO UNIQ_5387574AE48FD905BF1CD3C3');
        $this->addSql('ALTER INDEX IDX_463FD0A7E48FD905 RENAME TO IDX_5387574AE48FD905');
        $this->addSql('ALTER INDEX action_records_pkey RENAME TO events_pkey');
        $this->addSql('ALTER SEQUENCE action_records_id_seq RENAME TO events_id_seq');
        $this->addSql('ALTER TABLE action_records RENAME TO events');
    }
}
