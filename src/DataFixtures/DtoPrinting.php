<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('container.excluded')]
class DtoPrinting
{
    public string $cardId;
    public string $packId;
    public int $position;
    public int $quantity;
    public string $illustrator;
    public string $flavorText;
    public string $imageUrl;
}
