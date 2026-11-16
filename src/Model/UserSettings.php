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
    /** the pattern of the id of a tip of the tutorial, written by the web client */
    public const string TIP_PATTERN = '/^[a-z0-9_]{1,64}$/';

    /** the tips of the tutorial the user has seen, at most */
    public const int MAX_SEEN_TIPS = 200;

    /**
     * @param list<string> $seenTips
     */
    public function __construct(
        /** how the web client draws the cards on the board */
        public CardDisplay $cardDisplay = CardDisplay::Images,
        /** whether the web client is light or dark */
        public Theme $theme = Theme::Auto,
        /** whether the web client shows the tips of the tutorial in the games against the house bot */
        public bool $tutorial = true,
        /** the ids of the tips of the tutorial the user has seen (the catalogue is the web client's) */
        public array $seenTips = [],
    ) {
    }

    /**
     * @param array<mixed> $stored
     */
    public static function fromArray(array $stored): self
    {
        $cardDisplay = is_string($stored['card_display'] ?? null) ? CardDisplay::tryFrom($stored['card_display']) : null;
        $theme = is_string($stored['theme'] ?? null) ? Theme::tryFrom($stored['theme']) : null;
        $tutorial = $stored['tutorial'] ?? null;
        $seenTips = is_array($stored['seen_tips'] ?? null) ? $stored['seen_tips'] : [];

        return new self(
            $cardDisplay ?? CardDisplay::Images,
            $theme ?? Theme::Auto,
            is_bool($tutorial) ? $tutorial : true,
            self::validTips($seenTips),
        );
    }

    /**
     * @param array<mixed> $tips
     *
     * @return list<string> the valid ids, once each, the first kept up to the maximum
     */
    private static function validTips(array $tips): array
    {
        $valid = array_filter($tips, static fn (mixed $tip): bool => is_string($tip) && preg_match(self::TIP_PATTERN, $tip) === 1);

        return array_slice(array_values(array_unique($valid)), 0, self::MAX_SEEN_TIPS);
    }

    public function withCardDisplay(CardDisplay $cardDisplay): self
    {
        return new self($cardDisplay, $this->theme, $this->tutorial, $this->seenTips);
    }

    public function withTheme(Theme $theme): self
    {
        return new self($this->cardDisplay, $theme, $this->tutorial, $this->seenTips);
    }

    public function withTutorial(bool $tutorial): self
    {
        return new self($this->cardDisplay, $this->theme, $tutorial, $this->seenTips);
    }

    /**
     * @param list<string> $seenTips every tip seen, replacing those stored (empty: every tip shown again)
     */
    public function withSeenTips(array $seenTips): self
    {
        return new self($this->cardDisplay, $this->theme, $this->tutorial, self::validTips($seenTips));
    }

    /**
     * @return array{card_display: string, theme: string, tutorial: bool, seen_tips: list<string>}
     */
    public function toArray(): array
    {
        return [
            'card_display' => $this->cardDisplay->value,
            'theme' => $this->theme->value,
            'tutorial' => $this->tutorial,
            'seen_tips' => $this->seenTips,
        ];
    }
}
