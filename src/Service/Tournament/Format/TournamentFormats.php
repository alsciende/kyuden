<?php

declare(strict_types=1);

namespace App\Service\Tournament\Format;

use App\Enum\TournamentFormat;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The formats of the tournaments, each by its TournamentFormat.
 */
final readonly class TournamentFormats
{
    /**
     * @param iterable<TournamentFormatInterface> $formats
     */
    public function __construct(
        #[AutowireIterator(TournamentFormatInterface::class)]
        private iterable $formats,
    ) {
    }

    public function get(TournamentFormat $format): TournamentFormatInterface
    {
        $implementations = [];
        foreach ($this->formats as $implementation) {
            $implementations[$implementation->getFormat()->value] = $implementation;
        }

        return $implementations[$format->value] ?? throw new \LogicException("No implementation of the format {$format->value}.");
    }
}
