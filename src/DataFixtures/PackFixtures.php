<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Pack;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\SerializerInterface;

class PackFixtures extends Fixture
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly string $projectDir,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $finder = new Finder();
        $finder->files()->in($this->projectDir . '/fixtures/packs/')->name('*.json');
        foreach ($finder as $file) {
            /** @var Pack $pack */
            $pack = $this->serializer->deserialize($file->getContents(), Pack::class, 'json');
            $manager->persist($pack);
            $this->addReference($pack->getId(), $pack);
        }

        $manager->flush();
    }
}
