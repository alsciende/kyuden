<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Clan: string implements TranslatableInterface
{
    case Crab = 'crab';
    case Crane = 'crane';
    case Dragon = 'dragon';
    case Lion = 'lion';
    case Neutral = 'neutral';
    case Phoenix = 'phoenix';
    case Scorpion = 'scorpion';
    case Unicorn = 'unicorn';

    /**
     * @return list<self> the seven Great Clans, those a player prefers and who have a starter deck
     */
    public static function greatClans(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $clan): bool => $clan !== self::Neutral));
    }

    /**
     * The id of the starter deck of a Great Clan (a deck of the fixtures, without owner).
     */
    public function getStarterDeckId(): string
    {
        if ($this === self::Neutral) {
            throw new \LogicException('The neutral cards have no starter deck.');
        }

        return "{$this->value}-starter-deck";
    }

    #[\Override]
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->value, domain: 'clans', locale: $locale);
    }
}
