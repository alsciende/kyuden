<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ExternalCard;
use App\Entity\LogicalCard;
use App\Enum\CardSource;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Finder\Finder;

/**
 * One file per source in fixtures/external_cards (`<source>.json`): card code by external id.
 */
class ExternalCardFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [LogicalCardFixtures::class];
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $finder = new Finder();
        $finder->files()->in($this->projectDir . '/fixtures/external_cards/')->name('*.json')->sortByName();
        foreach ($finder as $file) {
            $source = CardSource::from($file->getFilenameWithoutExtension());
            /** @var array<string, string> $codes */
            $codes = json_decode($file->getContents(), true, flags: JSON_THROW_ON_ERROR);
            foreach ($codes as $externalId => $code) {
                $manager->persist(new ExternalCard($source, (string) $externalId, $this->getReference($code, LogicalCard::class)));
            }
        }

        $manager->flush();
    }
}
