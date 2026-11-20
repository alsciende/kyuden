<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A phase of a round (timing-and-gameplay.md), as the cards name it ("After the conflict phase begins").
 */
enum Phase: string
{
    case Dynasty = 'dynasty';
    case Draw = 'draw';
    case Conflict = 'conflict';
    case Fate = 'fate';

    /**
     * The phase a place of the `game` workflow belongs to, from its beginning to its end; null for the setup and the
     * end of the game.
     */
    public static function ofPlace(?string $place): ?self
    {
        return match ($place) {
            'dynasty_phase_begins', 'reveal_facedown_dynasty_cards', 'collect_fate', 'play_cards_from_provinces',
            'dynasty_phase_ends' => self::Dynasty,
            'draw_phase_begins', 'honor_bid', 'reveal_honor_dials', 'transfer_honor', 'draw_cards',
            'draw_phase_action_window', 'draw_phase_ends' => self::Draw,
            'conflict_phase_begins', 'conflict_phase_action_window', 'declare_conflict', 'reveal_attacked_province',
            'react_to_conflict_declaration', 'declare_defenders', 'conflict_action_window', 'determine_result',
            'react_to_conflict_result', 'apply_unopposed', 'break_province', 'react_to_broken_province',
            'resolve_ring_effects', 'claim_ring', 'react_to_ring_claim', 'return_home', 'conflict_ends',
            'determine_imperial_favor', 'glory_count', 'claim_imperial_favor', 'conflict_phase_ends' => self::Conflict,
            'fate_phase_begins', 'discard_characters_with_no_fate', 'remove_fate_from_characters',
            'place_fate_on_unclaimed_rings', 'fate_phase_action_window', 'ready_cards', 'discard_from_provinces',
            'return_rings', 'pass_first_player_token', 'fate_phase_ends' => self::Fate,
            default => null,
        };
    }
}
