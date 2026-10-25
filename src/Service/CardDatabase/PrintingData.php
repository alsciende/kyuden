<?php

declare(strict_types=1);

namespace App\Service\CardDatabase;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A printing as written in fixtures/printings: the card and the pack by id.
 */
#[AutoconfigureTag('container.excluded')]
class PrintingData
{
    public string $cardId;
    public string $packId;
    public int $position;
    public int $quantity;
    public string $illustrator;
    public string $flavorText;
    public string $imageUrl;
}
