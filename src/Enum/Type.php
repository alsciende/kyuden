<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Type: string implements TranslatableInterface
{
    case Attachment = 'attachment';
    case Character = 'character';
    case Event = 'event';
    case Holding = 'holding';
    case Province = 'province';
    case Role = 'role';
    case Stronghold = 'stronghold';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->value, domain: 'types', locale: $locale);
    }
}
