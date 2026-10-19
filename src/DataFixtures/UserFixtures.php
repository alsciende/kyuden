<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AccessToken;
use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Enum\UserType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * The users of the development and the tests, with their access tokens in clear: humans with a session that does not
 * expire, and bots owned by john with an API key, and the house bot owned by the platform. Each user is a reference named after its id. These tokens are public: never load the fixtures in production.
 */
class UserFixtures extends Fixture
{
    /**
     * The access token of each user, by user id.
     */
    public const array TOKENS = [
        'john' => 'john-dev-token',
        'jane' => 'jane-dev-token',
        'harry' => 'harry-dev-token',
        'sally' => 'sally-dev-token',
        'akodo-bot' => 'akodo-bot-dev-key',
        'hida-bot' => 'hida-bot-dev-key',
        'kyuden' => 'kyuden-dev-token',
        'kyuden-bot' => 'kyuden-bot-dev-key',
    ];

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $john = new User('john', 'John', UserType::Human);
        $jane = new User('jane', 'Jane', UserType::Human);
        // the platform, owner of the house bot (HOUSE_BOT_ID) that plays the introductory games
        $kyuden = new User('kyuden', 'Kyūden', UserType::Human);
        $users = [
            $john,
            $jane,
            new User('harry', 'Harry', UserType::Human),
            new User('sally', 'Sally', UserType::Human),
            new User('akodo-bot', 'Akodo Bot', UserType::Bot, $john),
            new User('hida-bot', 'Hida Bot', UserType::Bot, $john),
            $kyuden,
            new User('kyuden-bot', 'Kyūden Bot', UserType::Bot, $kyuden),
        ];
        foreach ($users as $user) {
            $manager->persist($user);
            $this->addReference($user->getId(), $user);
            $type = $user->getType() === UserType::Bot ? AccessTokenType::ApiKey : AccessTokenType::Session;
            $manager->persist(new AccessToken($user, self::TOKENS[$user->getId()], $type));
        }

        $manager->flush();
    }
}
