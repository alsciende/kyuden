<?php

declare(strict_types=1);

namespace App\State\Model;

/**
 * An imminent triggering condition of an interrupt window (ImminentTriggerInterface), as the state keeps it: its name
 * and its params (ids), public.
 */
class ImminentTriggerRecord
{
    /**
     * @param array<string, string> $params
     */
    public function __construct(
        public string $name,
        public array $params,
    ) {
    }
}
