<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\CardDisplay;
use App\Enum\Theme;

/**
 * The preferences of a user, stored as JSON on the user: a setting missing from what is stored takes its default
 * value, so that a new setting needs no migration.
 */
final readonly class UserSettings
{
    public function __construct(
        /** how the web client draws the cards on the board */
        public CardDisplay $cardDisplay = CardDisplay::Drawn,
        /** whether the web client is light or dark */
        public Theme $theme = Theme::Auto,
    ) {
    }

    /**
     * @param array<mixed> $stored
     */
    public static function fromArray(array $stored): self
    {
        $cardDisplay = is_string($stored['card_display'] ?? null) ? CardDisplay::tryFrom($stored['card_display']) : null;
        $theme = is_string($stored['theme'] ?? null) ? Theme::tryFrom($stored['theme']) : null;

        return new self($cardDisplay ?? CardDisplay::Drawn, $theme ?? Theme::Auto);
    }

    public function withCardDisplay(CardDisplay $cardDisplay): self
    {
        return new self($cardDisplay, $this->theme);
    }

    public function withTheme(Theme $theme): self
    {
        return new self($this->cardDisplay, $theme);
    }

    /**
     * @return array{card_display: string, theme: string}
     */
    public function toArray(): array
    {
        return ['card_display' => $this->cardDisplay->value, 'theme' => $this->theme->value];
    }
}
