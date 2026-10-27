<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027171226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The cycles of packs, the cycle of a pack and its position in it (written by app:cards:import; the packs already imported are given their cycle here)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cycles (id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, position INT NOT NULL, size INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE packs ADD position INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE packs ALTER position DROP DEFAULT');
        $this->addSql('ALTER TABLE packs ADD cycle_id VARCHAR DEFAULT NULL');
        // the cycles of the fixtures, so that the packs already imported get theirs before the column is required
        $this->addSql("INSERT INTO cycles (id, name, position, size) VALUES ('core', 'Core Set', 1, 1), ('clan-packs', 'Clan Packs', 2, 7), ('premium-expansions', 'Premium Expansions', 3, 1), ('imperial', 'Imperial Cycle', 4, 6), ('elemental', 'Elemental Cycle', 5, 6), ('inheritance', 'Inheritance Cycle', 6, 6), ('dominion', 'Dominion Cycle', 7, 6), ('temptations', 'Temptations Cycle', 8, 6)");
        $this->addSql("UPDATE packs SET cycle_id = 'core' WHERE id IN ('core')");
        $this->addSql("UPDATE packs SET cycle_id = 'clan-packs' WHERE id IN ('defenders-of-rokugan', 'disciples-of-the-void', 'masters-of-the-court', 'seekers-of-wisdom', 'the-emperor-s-legion', 'underhand-of-the-emperor', 'warriors-of-the-wind')");
        $this->addSql("UPDATE packs SET cycle_id = 'premium-expansions' WHERE id IN ('children-of-the-empire')");
        $this->addSql("UPDATE packs SET cycle_id = 'imperial' WHERE id IN ('fate-has-no-secrets', 'for-honor-and-glory', 'into-the-forbidden-city', 'meditations-on-the-ephemeral', 'tears-of-amaterasu', 'the-chrysanthemum-throne')");
        $this->addSql("UPDATE packs SET cycle_id = 'elemental' WHERE id IN ('all-and-nothing', 'breath-of-the-kami', 'elements-unbound', 'tainted-lands', 'the-ebb-and-flow', 'the-fires-within')");
        $this->addSql("UPDATE packs SET cycle_id = 'inheritance' WHERE id IN ('a-champion-s-foresight', 'bonds-of-blood', 'for-the-empire', 'justice-for-satsume', 'shoju-s-duty', 'the-children-of-heaven')");
        $this->addSql("UPDATE packs SET cycle_id = 'dominion' WHERE id IN ('as-honor-demands', 'atonement', 'campaigns-of-conquest', 'in-pursuit-of-truth', 'rokugan-at-war', 'spreading-shadows')");
        $this->addSql("UPDATE packs SET cycle_id = 'temptations' WHERE id IN ('a-crimson-offering', 'coils-of-power', 'honor-in-flames', 'peace-at-any-cost', 'the-temptation-of-the-scorpion', 'twisted-loyalties')");
        $this->addSql('ALTER TABLE packs ALTER cycle_id SET NOT NULL');
        $this->addSql('ALTER TABLE packs ADD CONSTRAINT FK_B9FE60275EC1162 FOREIGN KEY (cycle_id) REFERENCES cycles (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_B9FE60275EC1162 ON packs (cycle_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE packs DROP CONSTRAINT FK_B9FE60275EC1162');
        $this->addSql('DROP INDEX IDX_B9FE60275EC1162');
        $this->addSql('ALTER TABLE packs DROP position');
        $this->addSql('ALTER TABLE packs DROP cycle_id');
        $this->addSql('DROP TABLE cycles');
    }
}
