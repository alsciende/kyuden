<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Service\CardDatabase\CardDatabaseImporter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * The database of the cards, as in production (`app:cards:import`): packs, cards, printings, external cards and the
 * starter decks, without owner: every user can play them.
 */
class AppFixtures extends Fixture
{
    public const CRAB_STARTER_DECK = 'crab-starter-deck';
    public const CRANE_STARTER_DECK = 'crane-starter-deck';
    public const DRAGON_STARTER_DECK = 'dragon-starter-deck';
    public const LION_STARTER_DECK = 'lion-starter-deck';
    public const PHOENIX_STARTER_DECK = 'phoenix-starter-deck';
    public const SCORPION_STARTER_DECK = 'scorpion-starter-deck';
    public const UNICORN_STARTER_DECK = 'unicorn-starter-deck';

    public function __construct(
        private readonly CardDatabaseImporter $cardDatabaseImporter,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $this->cardDatabaseImporter->import();
    }
}
