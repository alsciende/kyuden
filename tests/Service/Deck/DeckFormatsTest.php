<?php

declare(strict_types=1);

namespace App\Tests\Service\Deck;

use App\Entity\ExternalCard;
use App\Entity\LogicalCard;
use App\Enum\CardSource;
use App\Service\Deck\CodesDeckFormat;
use App\Service\Deck\EmeraldDbDeckFormat;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class DeckFormatsTest extends AbstractKernelTestCase
{
    public function testEmeraldDbIdsAreConvertedToCodes(): void
    {
        $converted = self::service(EmeraldDbDeckFormat::class)->toCodes([
            'akodo-toturi' => 1,
            'akodo-toturi-2' => 2,
            'kuni-wasteland' => 1,
            'a-new-card' => 3,
        ]);

        self::assertSame(['01-akodo-toturi' => 1, '16-akodo-toturi' => 2, '26-kuni-wastelands' => 1], $converted['cards']);
        self::assertSame(['a-new-card'], $converted['unknown']);
    }

    public function testTheQuantitiesOfIdsNamingTheSameCardAreAddedUp(): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $card = $entityManager->find(LogicalCard::class, '01-banzai');
        self::assertInstanceOf(LogicalCard::class, $card);
        $entityManager->persist(new ExternalCard(CardSource::EmeraldDb, 'banzai-alias', $card));
        $entityManager->flush();

        $converted = self::service(EmeraldDbDeckFormat::class)->toCodes(['banzai' => 1, 'banzai-alias' => 2]);

        self::assertSame(['01-banzai' => 3], $converted['cards']);
    }

    public function testCodesAreKeptWhenTheyNameACard(): void
    {
        $converted = self::service(CodesDeckFormat::class)->toCodes(['01-banzai' => 3, 'banzai' => 1]);

        self::assertSame(['01-banzai' => 3], $converted['cards']);
        self::assertSame(['banzai'], $converted['unknown']);
    }
}
