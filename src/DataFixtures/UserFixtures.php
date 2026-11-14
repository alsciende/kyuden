<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Command\HouseBotCreateCommand;
use App\Entity\AccessToken;
use App\Entity\Deck;
use App\Entity\UnlockedMon;
use App\Entity\UnlockedPattern;
use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Enum\BannerPattern;
use App\Enum\Clan;
use App\Enum\Mon;
use App\Enum\UserType;
use App\Service\Deck\StarterDeckCopier;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Finder\Finder;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The users of the development and the tests, with their access tokens in clear: humans with a session that does not
 * expire, and bots owned by john with an API key, and the house bot owned by the platform. Each user is a reference
 * named after its id. john and jane have an account they sign in to (USERNAMES, PASSWORDS), with their preferred clan
 * and their copy of its starter deck (PREFERRED_CLANS), and the decks of `fixtures/user_decks` (USER_DECKS); harry and sally are guests. These tokens and passwords are public: never load the fixtures in production.
 */
class UserFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * The username of each human with an account, by user id.
     */
    public const array USERNAMES = [
        'john' => 'john',
        'jane' => 'jane',
    ];

    /**
     * The password of each human with an account, by user id.
     */
    public const array PASSWORDS = [
        'john' => 'john',
        'jane' => 'jane',
    ];

    /**
     * The preferred clan of each human with an account, by user id.
     */
    public const array PREFERRED_CLANS = [
        'john' => Clan::Crab,
        'jane' => Clan::Crane,
    ];

    /**
     * The decks of the users, one file per deck named after its id (`{"owner", "name", "cards"}`: quantity by card
     * code), out of the card database: `app:cards:import` does not read them.
     */
    public const string USER_DECKS = 'fixtures/user_decks';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly StarterDeckCopier $starterDeckCopier,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array<string, array{owner: string, name: string, cards: array<string, int>}> the decks of USER_DECKS, by id
     */
    public static function userDecks(string $projectDir): array
    {
        $decks = [];
        foreach (new Finder()->files()->in("{$projectDir}/" . self::USER_DECKS)->name('*.json')->sortByName() as $file) {
            /** @var array{owner: string, name: string, cards: array<string, int>} $deck */
            $deck = json_decode($file->getContents(), true, flags: \JSON_THROW_ON_ERROR);
            $decks[$file->getFilenameWithoutExtension()] = $deck;
        }

        return $decks;
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

    /**
     * When the tokens were last used: in the future, so that they never expire, whenever the database was loaded (the
     * tests roll back the uses they record).
     */
    public const string TOKENS_USED_AT = '2100-01-01';

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
            new User('kyuden-bot', HouseBotCreateCommand::NAME, UserType::Bot, $kyuden),
        ];
        foreach (self::USERNAMES as $id => $username) {
            $user = array_find($users, static fn (User $user): bool => $user->getId() === $id);
            \assert($user instanceof User);
            $user->register($username, $this->passwordHasher->hashPassword($user, self::PASSWORDS[$id]));
            $user->preferClan(self::PREFERRED_CLANS[$id]);
            $this->starterDeckCopier->copy(self::PREFERRED_CLANS[$id], $user);
        }
        foreach (self::userDecks($this->projectDir) as $id => $data) {
            $owner = array_find($users, static fn (User $user): bool => $user->getId() === $data['owner']);
            \assert($owner instanceof User);
            $manager->persist(new Deck($id)->setOwner($owner)->setName($data['name'])->setCards($data['cards']));
        }
        foreach ($users as $user) {
            $manager->persist($user);
            $this->addReference($user->getId(), $user);
            $type = $user->getType() === UserType::Bot ? AccessTokenType::ApiKey : AccessTokenType::Session;
            $manager->persist(new AccessToken($user, self::TOKENS[$user->getId()], $type, new \DateTimeImmutable(self::TOKENS_USED_AT)));
        }

        // john has earned a few mons and patterns, jane nothing yet: the choices of the nobori and what is locked
        $earnedAt = new \DateTimeImmutable(self::TOKENS_USED_AT);
        foreach ([Mon::Crab, Mon::Phoenix] as $mon) {
            $manager->persist(new UnlockedMon($john, $mon, $earnedAt));
        }
        foreach ([BannerPattern::Plain, BannerPattern::Asanoha, BannerPattern::Seigaiha] as $pattern) {
            $manager->persist(new UnlockedPattern($john, $pattern, $earnedAt));
        }

        $manager->flush();
    }
}
