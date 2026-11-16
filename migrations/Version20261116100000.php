<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The games against the house bot (training), those played before included';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games ADD training BOOLEAN DEFAULT false NOT NULL');

        // the games played before: those the house bot plays (their replays too, which keep the users of the players)
        $houseBotId = $_SERVER['HOUSE_BOT_ID'] ?? $_ENV['HOUSE_BOT_ID'] ?? getenv('HOUSE_BOT_ID');
        if (is_string($houseBotId) && $houseBotId !== '') {
            $this->addSql(
                'UPDATE games SET training = true WHERE id IN (SELECT game_id FROM players WHERE user_id = :houseBotId)',
                ['houseBotId' => $houseBotId],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games DROP training');
    }
}
