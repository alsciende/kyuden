/**
 * Builds the payload of a message from the arguments of a command and the JSON schema of the message
 * (`GET /api/actions`). The arguments are:
 * - positional, in the order of the properties of the schema: `/bow 0a19`;
 * - named: `card_cost=2`;
 * - or the payload itself, as a JSON object: `/send bow_card {"card_id": "..."}`.
 *
 * A card is given by reference (see GameModel::findCard), a list of cards separated by commas, a player by `me`, `opp`
 * or user id. A player property can be left out: it is the user (`/draw 3` draws 3 conflict cards for the user).
 */
export function buildPayload(schema, args, game) {
    if (args.length === 1 && args[0].startsWith('{')) {
        return JSON.parse(args[0]);
    }

    const properties = Object.entries(schema.properties ?? {});
    const required = new Set(schema.required ?? []);
    const named = new Map();
    const positional = [];
    for (const arg of args) {
        const match = arg.match(/^([a-z_]+)=(.*)$/s);
        if (match && properties.some(([name]) => name === match[1])) {
            named.set(match[1], match[2]);
        } else {
            positional.push(arg);
        }
    }

    const payload = {};
    const missing = [];
    properties.forEach(([name, property], index) => {
        let raw;
        if (named.has(name)) {
            raw = named.get(name);
        } else if (isPlayerProperty(name)) {
            if (positional.length > 0 && game?.findPlayer(positional[0])) {
                raw = positional.shift();
            } else if (name === 'target_player_id' || name === 'giver_id') {
                raw = 'me';
            }
        } else if (positional.length > 0) {
            const isLast = index === properties.length - 1;
            // the last free text property takes the rest of the line: /concede I have to go
            raw = isLast && isFreeText(property) ? positional.splice(0).join(' ') : positional.shift();
        }

        if (raw === undefined) {
            if (required.has(name)) {
                missing.push(name);
            }
            return;
        }
        payload[name] = convert(name, property, raw, game);
    });

    if (missing.length > 0) {
        throw new UsageError(`Missing: ${missing.join(', ')}.`);
    }
    if (positional.length > 0) {
        throw new UsageError(`Too many arguments: ${positional.join(' ')}.`);
    }

    return payload;
}

/** The usage of a message: `<card> <card_cost>`. */
export function usage(schema) {
    const required = new Set(schema.required ?? []);

    return Object.entries(schema.properties ?? {}).map(([name, property]) => {
        let label = name;
        if (isPlayerProperty(name)) {
            label = name === 'target_player_id' || name === 'giver_id' ? `${name.replace(/_id$/, '')}=me` : name.replace(/_id$/, '');
        } else if (isCardProperty(name)) {
            label = name.replace(/_ids?$/, (suffix) => (suffix === '_ids' ? 's,…' : ''));
        } else if (property.enum) {
            label = property.enum.join('|');
        }
        const optional = !required.has(name) || label.endsWith('=me');

        return optional ? `[${label}]` : `<${label}>`;
    }).join(' ');
}

export class UsageError extends Error {}

export function isPlayerProperty(name) {
    return name.endsWith('player_id') || name === 'giver_id' || name === 'recipient_id';
}

export function isCardProperty(name) {
    return !isPlayerProperty(name) && (name.endsWith('card_id') || name.endsWith('_ids') || name === 'province_id');
}

function types(property) {
    return Array.isArray(property.type) ? property.type : [property.type];
}

function isFreeText(property) {
    return types(property).includes('string') && !property.enum;
}

function convert(name, property, raw, game) {
    const propertyTypes = types(property);

    if (propertyTypes.includes('array')) {
        const items = Array.isArray(raw) ? raw : String(raw).split(',').map((item) => item.trim()).filter((item) => item !== '');
        return items.map((item) => convert(name, property.items ?? { type: 'string' }, item, game));
    }
    if (propertyTypes.includes('null') && (raw === 'null' || raw === '')) {
        return null;
    }
    if (propertyTypes.includes('integer')) {
        if (!/^-?\d+$/.test(raw)) {
            throw new UsageError(`${name} must be an integer, "${raw}" given.`);
        }
        return Number(raw);
    }
    if (propertyTypes.includes('boolean')) {
        const lower = String(raw).toLowerCase();
        if (['true', 'yes', 'y', '1', 'on'].includes(lower)) return true;
        if (['false', 'no', 'n', '0', 'off'].includes(lower)) return false;
        throw new UsageError(`${name} must be true or false, "${raw}" given.`);
    }
    if (property.enum) {
        const matches = property.enum.filter((value) => String(value).startsWith(String(raw).toLowerCase()));
        if (matches.length !== 1) {
            throw new UsageError(`${name} must be one of ${property.enum.join(', ')}, "${raw}" given.`);
        }
        return matches[0];
    }
    if (game && isPlayerProperty(name)) {
        const player = game.findPlayer(raw);
        if (player === null) {
            throw new UsageError(`No player matches "${raw}" (me, opp, or a user id).`);
        }
        return player.id;
    }
    if (game && isCardProperty(name)) {
        return game.findCard(raw).id;
    }

    return String(raw);
}
