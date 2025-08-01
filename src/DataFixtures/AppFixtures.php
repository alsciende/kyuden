<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Candidate;
use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\Player;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    use StarterDeckLionTrait;
    use StarterDeckCrabTrait;
    use StarterDeckDragonTrait;
    use StarterDeckPhoenixTrait;
    use StarterDeckCraneTrait;
    use StarterDeckScorpionTrait;
    use StarterDeckUnicornTrait;

    public const CRAB_STARTER_DECK = 'crab-starter-deck';
    public const CRANE_STARTER_DECK = 'crane-starter-deck';
    public const DRAGON_STARTER_DECK = 'dragon-starter-deck';
    public const LION_STARTER_DECK = 'lion-starter-deck';
    public const PHOENIX_STARTER_DECK = 'phoenix-starter-deck';
    public const SCORPION_STARTER_DECK = 'scorpion-starter-deck';
    public const UNICORN_STARTER_DECK = 'unicorn-starter-deck';

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $decks = [
            self::CRAB_STARTER_DECK => $this->getCrabStarterCards(),
            self::CRANE_STARTER_DECK => $this->getCraneStarterCards(),
            self::DRAGON_STARTER_DECK => $this->getDragonStarterCards(),
            self::LION_STARTER_DECK => $this->getLionStarterCards(),
            self::PHOENIX_STARTER_DECK => $this->getPhoenixStarterCards(),
            self::SCORPION_STARTER_DECK => $this->getScorpionStarterCards(),
            self::UNICORN_STARTER_DECK => $this->getUnicornStarterCards(),
        ];
        foreach ($decks as $deckId => $cards) {
            $deck = new Deck($deckId);
            $manager->persist($deck);
            $deck->setName($deckId);
            $deck->setContent($cards);
        }

        $john = new Candidate();
        $manager->persist($john);
        $john->setUserId('john');
        $john->setDeck($deck);

        $jane = new Candidate();
        $manager->persist($jane);
        $jane->setUserId('jane');
        $jane->setDeck($deck);

        $game = new Game('1');
        $manager->persist($game);

        $harry = new Player('1');
        $manager->persist($harry);
        $harry->setUserId('harry');
        $harry->setDeck($deck);
        $game->addPlayer($harry);

        $sally = new Player('2');
        $manager->persist($sally);
        $sally->setUserId('sally');
        $sally->setDeck($deck);
        $game->addPlayer($sally);

        $manager->flush();
    }
}
