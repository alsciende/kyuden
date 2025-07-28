<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\LogicalCard;
use App\Entity\Pack;
use App\Entity\Printing;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\SerializerInterface;

class PrintingFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly string $projectDir,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $LogicalCardRepository = $manager->getRepository(LogicalCard::class);
        $packRepository = $manager->getRepository(Pack::class);

        $finder = new Finder();
        $finder->files()->in($this->projectDir . '/fixtures/printings/')->name('*.json');
        foreach ($finder as $file) {
            /** @var DtoPrinting $dto */
            $dto = $this->serializer->deserialize($file->getContents(), DtoPrinting::class, 'json');
            $printing = new Printing();
            $card = $LogicalCardRepository->find($dto->cardId);
            if (! $card instanceof LogicalCard) {
                continue;
            }
            $printing->setLogicalCard($card);
            $pack = $packRepository->find($dto->packId);
            if (! $pack instanceof Pack) {
                continue;
            }
            $printing->setPack($pack);
            $printing->setIllustrator($dto->illustrator);
            $printing->setFlavorText($dto->flavorText);
            $printing->setPosition($dto->position);
            $printing->setImageUrl($dto->imageUrl);
            $printing->setQuantity($dto->quantity);
            $manager->persist($printing);
        }

        $manager->flush();
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            LogicalCardFixtures::class,
            PackFixtures::class,
        ];
    }
}
