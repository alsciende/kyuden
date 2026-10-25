<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025102839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE access_tokens_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE candidates_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE events_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE external_cards_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE game_log_entries_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE printings_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE access_tokens (id INT NOT NULL, user_id VARCHAR(255) NOT NULL, hash VARCHAR(64) NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_58D184BCD1B862B8 ON access_tokens (hash)');
        $this->addSql('CREATE INDEX IDX_58D184BCA76ED395 ON access_tokens (user_id)');
        $this->addSql('CREATE TABLE candidates (id INT NOT NULL, user_id VARCHAR(255) NOT NULL, deck_id VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A77F80CA76ED395 ON candidates (user_id)');
        $this->addSql('CREATE INDEX IDX_6A77F80C111948DC ON candidates (deck_id)');
        $this->addSql('CREATE TABLE decks (id VARCHAR(255) NOT NULL, owner_id VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, cards JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A3FCC6327E3C61F9 ON decks (owner_id)');
        $this->addSql('CREATE TABLE events (id INT NOT NULL, game_id VARCHAR(255) NOT NULL, version INT NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, payload jsonb NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5387574AE48FD905 ON events (game_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5387574AE48FD905BF1CD3C3 ON events (game_id, version)');
        $this->addSql('COMMENT ON COLUMN events.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE external_cards (id INT NOT NULL, card_id VARCHAR(50) NOT NULL, source VARCHAR(255) NOT NULL, external_id VARCHAR(100) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_368F2B7E4ACC9A20 ON external_cards (card_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_368F2B7E5F8A7F739F75D7B0 ON external_cards (source, external_id)');
        $this->addSql('CREATE TABLE game_log_entries (id INT NOT NULL, game_id VARCHAR(255) NOT NULL, version INT NOT NULL, template TEXT NOT NULL, params JSONB NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CB60C950E48FD905 ON game_log_entries (game_id)');
        $this->addSql('CREATE INDEX IDX_CB60C950E48FD905BF1CD3C3 ON game_log_entries (game_id, version)');
        $this->addSql('CREATE TABLE games (id VARCHAR(255) NOT NULL, current_place VARCHAR(255) DEFAULT NULL, state TEXT NOT NULL, version INT NOT NULL, random_engine TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE logical_cards (id VARCHAR(50) NOT NULL, name VARCHAR(50) NOT NULL, clan VARCHAR(255) NOT NULL, cost INT DEFAULT NULL, type VARCHAR(255) NOT NULL, traits text[] DEFAULT NULL, text VARCHAR(1024) DEFAULT NULL, allowed_clans text[] DEFAULT NULL, deck_limit INT DEFAULT NULL, elements text[] DEFAULT NULL, fate INT DEFAULT NULL, glory INT DEFAULT NULL, honor INT DEFAULT NULL, influence_cost INT DEFAULT NULL, influence_pool INT DEFAULT NULL, military VARCHAR(5) DEFAULT NULL, military_bonus VARCHAR(5) DEFAULT NULL, political VARCHAR(5) DEFAULT NULL, political_bonus VARCHAR(5) DEFAULT NULL, role_restriction VARCHAR(255) DEFAULT NULL, side VARCHAR(255) NOT NULL, strength VARCHAR(5) DEFAULT NULL, strength_bonus VARCHAR(5) DEFAULT NULL, uniqueness BOOLEAN NOT NULL, ability_timings text[] NOT NULL, ability_granted_to_attached BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE packs (id VARCHAR(255) NOT NULL, shorthand VARCHAR(5) NOT NULL, name VARCHAR(255) NOT NULL, size INT DEFAULT NULL, release_date DATE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE physical_cards (id VARCHAR(255) NOT NULL, logical_card_id VARCHAR(50) NOT NULL, game_id VARCHAR(255) NOT NULL, player_id VARCHAR(255) NOT NULL, stack_id VARCHAR(255) NOT NULL, orientation VARCHAR(20) NOT NULL, title VARCHAR(255) DEFAULT NULL, position INT DEFAULT NULL, state TEXT NOT NULL, side VARCHAR(10) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_96744D8BDAC81753 ON physical_cards (logical_card_id)');
        $this->addSql('CREATE INDEX IDX_96744D8BE48FD905 ON physical_cards (game_id)');
        $this->addSql('CREATE INDEX IDX_96744D8B99E6F5DF ON physical_cards (player_id)');
        $this->addSql('CREATE INDEX IDX_96744D8B37C70060 ON physical_cards (stack_id)');
        $this->addSql('CREATE TABLE players (id VARCHAR(255) NOT NULL, game_id VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, deck_id VARCHAR(255) NOT NULL, state TEXT NOT NULL, join_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, join_rank INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_264E43A6E48FD905 ON players (game_id)');
        $this->addSql('CREATE INDEX IDX_264E43A6A76ED395 ON players (user_id)');
        $this->addSql('CREATE INDEX IDX_264E43A6111948DC ON players (deck_id)');
        $this->addSql('COMMENT ON COLUMN players.join_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE printings (id INT NOT NULL, card_id VARCHAR(50) NOT NULL, pack_id VARCHAR(255) NOT NULL, quantity INT NOT NULL, position INT NOT NULL, illustrator VARCHAR(255) DEFAULT NULL, flavor_text VARCHAR(1023) DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3F8AE3C04ACC9A20 ON printings (card_id)');
        $this->addSql('CREATE INDEX IDX_3F8AE3C01919B217 ON printings (pack_id)');
        $this->addSql('CREATE TABLE stack (id VARCHAR(255) NOT NULL, player_id VARCHAR(255) NOT NULL, type INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_41A87B6A99E6F5DF ON stack (player_id)');
        $this->addSql('CREATE TABLE users (id VARCHAR(255) NOT NULL, owner_id VARCHAR(255) DEFAULT NULL, name VARCHAR(255) NOT NULL, username VARCHAR(32) DEFAULT NULL, username_canonical VARCHAR(32) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, type VARCHAR(255) NOT NULL, preferred_clan VARCHAR(16) DEFAULT NULL, settings JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E992FC23A8 ON users (username_canonical)');
        $this->addSql('CREATE INDEX IDX_1483A5E97E3C61F9 ON users (owner_id)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE access_tokens ADD CONSTRAINT FK_58D184BCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE candidates ADD CONSTRAINT FK_6A77F80CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE candidates ADD CONSTRAINT FK_6A77F80C111948DC FOREIGN KEY (deck_id) REFERENCES decks (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE decks ADD CONSTRAINT FK_A3FCC6327E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AE48FD905 FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE external_cards ADD CONSTRAINT FK_368F2B7E4ACC9A20 FOREIGN KEY (card_id) REFERENCES logical_cards (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE game_log_entries ADD CONSTRAINT FK_CB60C950E48FD905 FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE physical_cards ADD CONSTRAINT FK_96744D8BDAC81753 FOREIGN KEY (logical_card_id) REFERENCES logical_cards (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE physical_cards ADD CONSTRAINT FK_96744D8BE48FD905 FOREIGN KEY (game_id) REFERENCES games (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE physical_cards ADD CONSTRAINT FK_96744D8B99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE physical_cards ADD CONSTRAINT FK_96744D8B37C70060 FOREIGN KEY (stack_id) REFERENCES stack (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT FK_264E43A6E48FD905 FOREIGN KEY (game_id) REFERENCES games (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT FK_264E43A6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT FK_264E43A6111948DC FOREIGN KEY (deck_id) REFERENCES decks (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE printings ADD CONSTRAINT FK_3F8AE3C04ACC9A20 FOREIGN KEY (card_id) REFERENCES logical_cards (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE printings ADD CONSTRAINT FK_3F8AE3C01919B217 FOREIGN KEY (pack_id) REFERENCES packs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stack ADD CONSTRAINT FK_41A87B6A99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E97E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE access_tokens_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE candidates_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE events_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE external_cards_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE game_log_entries_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE printings_id_seq CASCADE');
        $this->addSql('ALTER TABLE access_tokens DROP CONSTRAINT FK_58D184BCA76ED395');
        $this->addSql('ALTER TABLE candidates DROP CONSTRAINT FK_6A77F80CA76ED395');
        $this->addSql('ALTER TABLE candidates DROP CONSTRAINT FK_6A77F80C111948DC');
        $this->addSql('ALTER TABLE decks DROP CONSTRAINT FK_A3FCC6327E3C61F9');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT FK_5387574AE48FD905');
        $this->addSql('ALTER TABLE external_cards DROP CONSTRAINT FK_368F2B7E4ACC9A20');
        $this->addSql('ALTER TABLE game_log_entries DROP CONSTRAINT FK_CB60C950E48FD905');
        $this->addSql('ALTER TABLE physical_cards DROP CONSTRAINT FK_96744D8BDAC81753');
        $this->addSql('ALTER TABLE physical_cards DROP CONSTRAINT FK_96744D8BE48FD905');
        $this->addSql('ALTER TABLE physical_cards DROP CONSTRAINT FK_96744D8B99E6F5DF');
        $this->addSql('ALTER TABLE physical_cards DROP CONSTRAINT FK_96744D8B37C70060');
        $this->addSql('ALTER TABLE players DROP CONSTRAINT FK_264E43A6E48FD905');
        $this->addSql('ALTER TABLE players DROP CONSTRAINT FK_264E43A6A76ED395');
        $this->addSql('ALTER TABLE players DROP CONSTRAINT FK_264E43A6111948DC');
        $this->addSql('ALTER TABLE printings DROP CONSTRAINT FK_3F8AE3C04ACC9A20');
        $this->addSql('ALTER TABLE printings DROP CONSTRAINT FK_3F8AE3C01919B217');
        $this->addSql('ALTER TABLE stack DROP CONSTRAINT FK_41A87B6A99E6F5DF');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E97E3C61F9');
        $this->addSql('DROP TABLE access_tokens');
        $this->addSql('DROP TABLE candidates');
        $this->addSql('DROP TABLE decks');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE external_cards');
        $this->addSql('DROP TABLE game_log_entries');
        $this->addSql('DROP TABLE games');
        $this->addSql('DROP TABLE logical_cards');
        $this->addSql('DROP TABLE packs');
        $this->addSql('DROP TABLE physical_cards');
        $this->addSql('DROP TABLE players');
        $this->addSql('DROP TABLE printings');
        $this->addSql('DROP TABLE stack');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
