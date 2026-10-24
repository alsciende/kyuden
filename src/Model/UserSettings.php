<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\CardDisplay;

/**
 * The preferences of a user, stored as JSON on the user: a setting missing from what is stored takes its default
 * value, so that a new setting needs no migration.
 */
final readonly class UserSettings
{
    public function __construct(
        /** how the web client draws the cards on the board */
        public CardDisplay $cardDisplay = CardDisplay::Drawn,
    ) {
    }

    /**
     * @param array<mixed> $stored
     */
    public static function fromArray(array $stored): self
    {
        $cardDisplay = is_string($stored['card_display'] ?? null) ? CardDisplay::tryFrom($stored['card_display']) : null;

        return new self($cardDisplay ?? CardDisplay::Drawn);
    }

    public function withCardDisplay(CardDisplay $cardDisplay): self
    {
        return new self($cardDisplay);
    }

    /**
     * @return array{card_display: string}
     */
    public function toArray(): array
    {
        return ['card_display' => $this->cardDisplay->value];
    }
}
