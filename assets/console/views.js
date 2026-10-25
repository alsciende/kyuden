import { seg } from './terminal.js';
import { cardStats, plainText } from './game.js';

/**
 * What the view commands write: the state of the game, the stacks of the players and the log, from a GameModel.
 */

export const STACK_LABELS = {
    conflict_draw_deck: 'conflict deck',
    conflict_discard_pile: 'conflict discard pile',
    dynasty_draw_deck: 'dynasty deck',
    dynasty_discard_pile: 'dynasty discard pile',
    stronghold_province: 'stronghold province',
    role: 'role',
    hand: 'hand',
    regular_province: 'province',
    in_play: 'play area',
};

export function writeState(block, game) {
    const view = game.view;
    block.line([seg('place      ', 'dim'), seg(view.place ?? '—', 'place'), seg(`   version ${view.version}`, 'dim')]);
    block.line([seg('first      ', 'dim'), seg(game.playerName(view.first_player_id))]);
    block.line([seg('active     ', 'dim'), seg(view.active_player_ids.map((id) => game.playerName(id)).join(', ') || '—', view.active_player_ids.includes(view.viewer_id) ? 'success' : null)]);
    for (const player of view.players) {
        writePlayerSummary(block, game, player);
    }
    writeRings(block, game);
    if (view.imperial_favor_holder_id) {
        block.line([seg('favor      ', 'dim'), seg(`${game.playerName(view.imperial_favor_holder_id)} (${view.imperial_favor_side ?? 'unset'})`)]);
    }
    if (view.conflict) {
        block.line(seg('conflict', 'dim')).json(game.describe(view.conflict));
    }
    if (view.duel) {
        block.line(seg('duel', 'dim')).json(game.describe(view.duel));
    }
    if (view.winner_id) {
        block.success(`${game.playerName(view.winner_id)} wins (${view.victory_condition}).`);
    }
    writeAvailableActions(block, view);
}

export function writePlayerSummary(block, game, player) {
    const facts = [
        `honor ${player.honor ?? '?'}`,
        `fate ${player.fate}`,
    ];
    if (player.honor_bid !== null) facts.push(`bid ${player.honor_bid}`);
    else if (player.pending_honor_bid !== null) facts.push(`secret bid ${player.pending_honor_bid}`);
    else if (player.honor_bid_selected) facts.push('bid selected');
    if (player.passed) facts.push('passed');
    if (player.acted) facts.push('resolving their action');
    if (player.resolving_interrupts_and_reactions > 0) facts.push(`resolving ${player.resolving_interrupts_and_reactions} interrupt/reaction`);
    if (player.fate_collected) facts.push('fate collected');
    facts.push(`${player.conflict_opportunities} conflict opportunities`);
    if (player.declared_conflict_types.length > 0) facts.push(`declared ${player.declared_conflict_types.join(', ')}`);
    if (player.glory_count !== null) facts.push(`glory ${player.glory_count}`);
    const counts = player.stacks
        .filter((stack) => ['hand', 'conflict_draw_deck', 'dynasty_draw_deck', 'conflict_discard_pile', 'dynasty_discard_pile'].includes(stack.type))
        .map((stack) => `${STACK_LABELS[stack.type]} ${stack.count}`);

    block.line([seg(game.playerName(player.id).padEnd(11), 'player'), seg(facts.join(' · '))]);
    block.line([seg(''.padEnd(11)), seg(counts.join(' · '), 'dim')]);
}

export function writeRings(block, game) {
    const rings = Object.entries(game.view.rings).map(([element, ring]) => {
        const claimed = ring.claimed_by_id ? ` claimed by ${game.playerName(ring.claimed_by_id)}` : '';
        return `${element}${ring.fate > 0 ? ` (${ring.fate} fate)` : ''}${claimed}`;
    });
    block.line([seg('rings      ', 'dim'), seg(rings.join(' · '))]);
}

/**
 * The messages accepted in any place, from any player (board manipulations, interrupts and reactions, conceding): the
 * engine does not know which ability is being resolved. They are shown apart from the actions of the step.
 */
export const ANYTIME_ACTIONS = new Set([
    'bow_card', 'ready_card', 'discard_card', 'discard_random_card_from_hand', 'honor_character', 'dishonor_character',
    'draw_conflict_cards', 'fill_province', 'reveal_dynasty_card', 'reveal_province', 'gain_honor', 'lose_honor', 'gain_fate',
    'lose_fate', 'set_honor_bid', 'transfer_honor', 'place_fate', 'remove_fate', 'shuffle_conflict_deck', 'shuffle_dynasty_deck', 'move_to_conflict',
    'move_home', 'switch_conflict_type', 'switch_contested_ring', 'play_interrupt_event', 'play_reaction_event', 'trigger_interrupt_ability', 'trigger_reaction_ability',
    'initiate_duel', 'concede_game', 'point_at_card',
]);

export function stepActions(view) {
    return view.available_actions.filter((type) => !ANYTIME_ACTIONS.has(type));
}

export function writeAvailableActions(block, view) {
    const step = stepActions(view);
    const anytime = view.available_actions.length - step.length;
    block.line([
        seg('you can    ', 'dim'),
        seg(step.join(' ') || 'no action of the step', step.length > 0 ? 'actions' : 'dim'),
        seg(anytime > 0 ? `  + ${anytime} effects accepted anytime (/actions)` : '', 'dim'),
    ]);
}

/** The cards of the stacks of the given types, each stack on its own (provinces, characters in play…). */
export function writeStacks(block, game, players, types, { emptyText = '(empty)', showHidden = true } = {}) {
    for (const player of players) {
        block.line(seg(game.playerName(player.id), 'player'));
        const stacks = player.stacks.filter((stack) => types.includes(stack.type));
        if (stacks.length === 0) {
            block.dim(`  ${emptyText}`);
        }
        let provinces = 0;
        stacks.forEach((stack) => {
            const label = stack.type === 'regular_province' ? `province ${++provinces}` : STACK_LABELS[stack.type] ?? stack.type;
            if (stack.cards === null) {
                if (showHidden) {
                    block.line([seg(`  ${label}`, 'dim'), seg(`  ${stack.count} hidden cards`, 'hidden')]);
                }
                return;
            }
            if (stack.cards.length === 0) {
                block.line([seg(`  ${label}`, 'dim'), seg('  (empty)', 'dim')]);
                return;
            }
            if (types.length > 1 || stack.type !== 'in_play') {
                block.line(seg(`  ${label}`, 'dim'));
            }
            stack.cards.forEach((card, position) => {
                const prefix = stack.type === 'in_play' && position > 0 ? '    ↳ ' : '    ';
                block.line([seg(prefix), ...game.cardSegments(card)]);
            });
        });
    }
}

/**
 * The characters of one side of the conflict being resolved (`attacker` or `defender`), each with its attachments,
 * and the skill the side declared.
 */
export function writeParticipants(block, game, side) {
    const conflict = game.view.conflict;
    if (!conflict) {
        block.dim('No conflict.');
        return;
    }
    const playerId = side === 'attacker' ? conflict.attacking_player_id : conflict.defending_player_id;
    const ids = (side === 'attacker' ? conflict.attacker_ids : conflict.defender_ids) ?? [];
    const skill = side === 'attacker' ? conflict.attacker_skill : conflict.defender_skill;
    block.line([
        seg(game.playerName(playerId), 'player'),
        seg(` ${side === 'attacker' ? 'attacks' : 'defends'}`, 'dim'),
        seg(`  ${conflict.type} conflict · ${conflict.ring} ring`, 'place'),
        seg(skill === null || skill === undefined ? '  skill not declared' : `  skill ${skill}`, skill === null || skill === undefined ? 'dim' : 'value'),
    ]);
    if (ids.length === 0) {
        block.dim(`  no ${side}`);
    }
    for (const id of ids) {
        const card = game.cards.get(id);
        if (!card) {
            block.line([seg('  '), seg(`[${game.ref(id)}]`, 'ref'), seg(' unknown card', 'hidden')]);
            continue;
        }
        block.line([seg('  '), ...game.cardSegments(card)]);
        for (const attachment of game.stackOfCard.get(id)?.stack.cards ?? []) {
            if (attachment.id !== id) {
                block.line([seg('    ↳ '), ...game.cardSegments(attachment)]);
            }
        }
    }
}

/** A single list of cards: the hand, a discard pile. */
export function writeCards(block, game, cards, emptyText = '(no card)') {
    if (cards.length === 0) {
        block.dim(`  ${emptyText}`);
    }
    for (const card of [...cards].reverse()) {
        block.line([seg('  '), ...game.cardSegments(card)]);
    }
}

export function writeLogicalCard(block, logical, physical = null, game = null) {
    block.line([
        ...(physical && game ? [seg(`[${game.ref(physical.id)}] `, 'ref', logical.code)] : []),
        seg(logical.name, 'card', logical.code),
        seg(`  ${logical.code}`, 'dim'),
    ]);
    block.line(seg([logical.side, logical.type, logical.clan, logical.uniqueness ? 'unique' : null].filter(Boolean).join(' · '), 'dim'));
    const stats = cardStats(logical);
    if (stats !== '') {
        block.line(seg(stats, 'stats'));
    }
    if (logical.elements?.length) {
        block.line(seg(`elements: ${logical.elements.join(', ')}`, 'dim'));
    }
    if (logical.traits?.length) {
        block.line(seg(logical.traits.join('. ') + '.', 'traits'));
    }
    for (const paragraph of plainText(logical.text).split('\n')) {
        if (paragraph.trim() !== '') {
            block.line(paragraph.trim());
        }
    }
    if (logical.ability_timings?.length) {
        block.dim(`abilities: ${logical.ability_timings.join(', ')}${logical.ability_granted_to_attached ? ' (granted to the attached character)' : ''}`);
    }
}

/** A message of the log of the game, with its entries. */
export function writeLoggedMessage(block, game, message) {
    block.line([
        seg(`v${message.version} `, 'dim'),
        seg(message.player_id ? game.playerName(message.player_id) : 'platform', 'player'),
        seg(` ${message.type}`, 'type'),
    ]);
    for (const entry of message.entries) {
        block.line([seg('    '), ...renderTemplate(game, entry)]);
    }
}

function renderTemplate(game, entry) {
    const segments = [];
    const pattern = /\{(\w+)\}/g;
    let last = 0;
    for (const match of entry.template.matchAll(pattern)) {
        segments.push(seg(entry.template.slice(last, match.index)));
        const param = entry.params[match[1]];
        segments.push(...(param === undefined ? [seg(match[0], 'dim')] : renderParam(game, param)));
        last = match.index + match[0].length;
    }
    segments.push(seg(entry.template.slice(last)));

    return segments;
}

function renderParam(game, param) {
    switch (param.type) {
        case 'player':
            return [seg(game.playerName(param.id), 'player')];
        case 'card': {
            if (param.id === null) {
                return [seg('a card', 'hidden')];
            }
            const logical = game.logical(param.code);
            return [
                seg(`[${game.ref(param.id)}] `, 'ref', param.code),
                seg(logical?.name ?? param.code ?? 'face-down card', param.code ? 'card' : 'hidden', param.code),
            ];
        }
        case 'stack':
            return [seg(`${game.possessive(param.player_id)} ${STACK_LABELS[param.stack_type] ?? param.stack_type}`, 'stack')];
        case 'ring':
            return [seg(`${param.element} ring`, 'ring')];
        case 'value':
            return [seg(param.hidden ? '?' : String(param.value), param.hidden ? 'hidden' : 'value')];
        case 'list': {
            const segments = [];
            param.items.forEach((item, index) => {
                if (index > 0) segments.push(seg(', '));
                segments.push(...renderParam(game, item));
            });
            return segments.length > 0 ? segments : [seg('nothing', 'dim')];
        }
        default:
            return [seg(JSON.stringify(param), 'dim')];
    }
}
