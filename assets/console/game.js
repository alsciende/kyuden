import { seg } from './terminal.js';

/**
 * The game as the user sees it (`GET /api/games/{id}`), with what the console needs to show it and to read the
 * arguments of the commands: the short reference of each card (the shortest unique prefix of its id, at least 4
 * characters), the cards as printed (by code) and the players by id.
 */
export class GameModel {
    constructor(view, catalogue) {
        this.view = view;
        this.catalogue = catalogue;
        this.cards = new Map();
        this.stackOfCard = new Map();
        this.players = new Map(view.players.map((player) => [player.id, player]));
        for (const player of view.players) {
            for (const stack of player.stacks) {
                for (const card of stack.cards ?? []) {
                    this.cards.set(card.id, card);
                    this.stackOfCard.set(card.id, { stack, player });
                }
            }
        }
        this.refs = shortRefs([...this.cards.keys()]);
    }

    get me() {
        return this.players.get(this.view.viewer_id);
    }

    get opponents() {
        return this.view.players.filter((player) => player.id !== this.view.viewer_id);
    }

    ref(cardId) {
        return this.refs.get(cardId) ?? cardId.slice(0, 8);
    }

    logical(code) {
        return code === null || code === undefined ? null : this.catalogue.get(code) ?? null;
    }

    playerName(playerId) {
        if (playerId === null || playerId === undefined) {
            return '—';
        }
        const player = this.players.get(playerId);
        if (!player) {
            return playerId;
        }

        return player.id === this.view.viewer_id ? `${player.user_id} (you)` : player.user_id;
    }

    possessive(playerId) {
        return playerId === this.view.viewer_id ? 'your' : `${this.playerName(playerId)}'s`;
    }

    stacks(player, type) {
        return player.stacks.filter((stack) => stack.type === type);
    }

    /** The segments describing a card: `[0a19] Shiro Nishiyama  stronghold · bowed · 2 fate`. */
    cardSegments(card) {
        const logical = this.logical(card.code);
        const segments = [seg(`[${this.ref(card.id)}]`, 'ref', card.code), seg(' ')];
        if (logical === null) {
            segments.push(seg(card.code ?? 'face-down card', card.code ? 'card' : 'hidden'));
        } else {
            segments.push(seg(logical.name, 'card', card.code));
            segments.push(seg(`  ${[logical.type, logical.clan].filter(Boolean).join(', ')}`, 'dim'));
            const stats = cardStats(logical);
            if (stats !== '') {
                segments.push(seg(`  ${stats}`, 'stats'));
            }
        }
        const marks = [];
        if (card.orientation === 'sideways') marks.push('bowed');
        if (card.orientation === 'face_down' && card.code !== null) marks.push('face down');
        if (card.orientation === 'upside_down') marks.push('broken');
        if (card.fate > 0) marks.push(`${card.fate} fate`);
        if (card.personal_honor !== 'ordinary') marks.push(card.personal_honor);
        if (this.view.conflict?.attacker_ids?.includes?.(card.id)) marks.push('attacking');
        if (this.view.conflict?.defender_ids?.includes?.(card.id)) marks.push('defending');
        if (marks.length > 0) {
            segments.push(seg(`  · ${marks.join(' · ')}`, 'mark'));
        }

        return segments;
    }

    /** A card by reference: id, unique id prefix (with or without `#`), or part of the name of a card the user sees. */
    findCard(raw) {
        const text = String(raw).replace(/^[#[]/, '').replace(/]$/, '');
        if (this.cards.has(text)) {
            return this.cards.get(text);
        }
        const lower = text.toLowerCase();
        const byPrefix = [...this.cards.values()].filter((card) => card.id.startsWith(lower));
        if (byPrefix.length === 1) {
            return byPrefix[0];
        }
        if (byPrefix.length > 1) {
            throw new AmbiguousError(raw, byPrefix);
        }
        const byName = [...this.cards.values()].filter((card) => {
            const logical = this.logical(card.code);
            return card.code === lower || (logical !== null && logical.name.toLowerCase().includes(lower));
        });
        if (byName.length === 1) {
            return byName[0];
        }
        if (byName.length > 1) {
            throw new AmbiguousError(raw, byName);
        }

        throw new Error(`No card matches "${raw}" among the cards you see.`);
    }

    /** A player by reference: me, opp, their user id or their player id (or a prefix of it). */
    findPlayer(raw) {
        const text = String(raw).toLowerCase();
        if (['me', 'self', 'i'].includes(text)) {
            return this.me;
        }
        if (['opp', 'opponent', 'them', 'other'].includes(text) && this.opponents.length === 1) {
            return this.opponents[0];
        }
        const found = this.view.players.filter((player) => player.id === raw || player.user_id.toLowerCase() === text || player.id.startsWith(text));
        if (found.length === 1) {
            return found[0];
        }

        return null;
    }

    /** A value with the card and player ids it holds replaced by a readable label, to show the conflict or the duel. */
    describe(value) {
        return JSON.parse(JSON.stringify(value, (key, item) => {
            if (typeof item !== 'string') {
                return item;
            }
            if (this.cards.has(item)) {
                const card = this.cards.get(item);
                return `[${this.ref(item)}] ${this.logical(card.code)?.name ?? card.code ?? 'face-down card'}`;
            }
            if (this.players.has(item)) {
                return this.playerName(item);
            }

            return item;
        }));
    }
}

export class AmbiguousError extends Error {
    constructor(raw, cards) {
        super(`"${raw}" matches ${cards.length} cards, use their reference.`);
        this.cards = cards;
    }
}

export function cardStats(logical) {
    const parts = [];
    if (logical.cost !== null && logical.cost !== undefined) parts.push(`cost ${logical.cost}`);
    if (logical.military !== null && logical.military !== undefined) parts.push(`mil ${logical.military}`);
    if (logical.military_bonus && logical.military === null && logical.type === 'attachment') parts.push(`mil ${logical.military_bonus}`);
    if (logical.political !== null && logical.political !== undefined) parts.push(`pol ${logical.political}`);
    if (logical.political_bonus && logical.political === null && logical.type === 'attachment') parts.push(`pol ${logical.political_bonus}`);
    if (logical.glory !== null && logical.glory !== undefined) parts.push(`glory ${logical.glory}`);
    if (logical.strength !== null && logical.strength !== undefined) parts.push(`strength ${logical.strength}`);
    if (logical.strength_bonus) parts.push(`strength ${logical.strength_bonus}`);
    if (logical.fate !== null && logical.fate !== undefined && logical.type === 'stronghold') parts.push(`fate ${logical.fate}`);
    if (logical.honor !== null && logical.honor !== undefined && logical.type === 'stronghold') parts.push(`honor ${logical.honor}`);

    return parts.join(' · ');
}

/** The text of a card without its markup. */
export function plainText(html) {
    if (!html) {
        return '';
    }
    // an inert document: nothing of the markup is loaded nor run
    const parsed = new DOMParser().parseFromString(html.replaceAll(/<br\s*\/?>/gi, '\n'), 'text/html');

    return parsed.body.textContent ?? '';
}

function shortRefs(ids) {
    const refs = new Map();
    for (const id of ids) {
        let length = 4;
        while (length < id.length && ids.some((other) => other !== id && other.startsWith(id.slice(0, length)))) {
            length++;
        }
        refs.set(id, id.slice(0, length));
    }

    return refs;
}
