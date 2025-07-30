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

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->value, domain: 'clans', locale: $locale);
    }
}
