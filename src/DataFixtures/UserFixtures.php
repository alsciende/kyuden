<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AccessToken;
use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Enum\Clan;
use App\Enum\UserType;
use App\Service\Deck\StarterDeckCopier;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The users of the development and the tests, with their access tokens in clear: humans with a session that does not
 * expire, and bots owned by john with an API key, and the house bot owned by the platform. Each user is a reference
 * named after its id. john and jane have an account they sign in to (USERNAMES, PASSWORDS), with their preferred clan
 * and their copy of its starter deck (PREFERRED_CLANS); harry and sally are guests. These tokens and passwords are public: never load the fixtures in production.
 */
class UserFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * The username of each human with an account, by user id.
     */
    public const array USERNAMES = [
        'john' => 'john',
        'jane' => 'Jane_Doe',
    ];

    /**
     * The password of each human with an account, by user id.
     */
    public const array PASSWORDS = [
        'john' => 'john-dev-password',
        'jane' => 'jane-dev-password',
    ];

    /**
     * The preferred clan of each human with an account, by user id.
     */
    public const array PREFERRED_CLANS = [
        'john' => Clan::Crab,
        'jane' => Clan::Crane,
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly StarterDeckCopier $starterDeckCopier,
    ) {
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

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
        foreach (self::USERNAMES as $id => $username) {
            $user = array_find($users, static fn (User $user): bool => $user->getId() === $id);
            \assert($user instanceof User);
            $user->register($username, $this->passwordHasher->hashPassword($user, self::PASSWORDS[$id]));
            $user->preferClan(self::PREFERRED_CLANS[$id]);
            $this->starterDeckCopier->copy(self::PREFERRED_CLANS[$id], $user);
        }
        foreach ($users as $user) {
            $manager->persist($user);
            $this->addReference($user->getId(), $user);
            $type = $user->getType() === UserType::Bot ? AccessTokenType::ApiKey : AccessTokenType::Session;
            $manager->persist(new AccessToken($user, self::TOKENS[$user->getId()], $type));
        }

        $manager->flush();
    }
}
