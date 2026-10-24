import { AmbiguousError } from './game.js';
import { buildPayload, usage, UsageError } from './payload.js';
import { seg } from './terminal.js';
import {
    ANYTIME_ACTIONS,
    STACK_LABELS,
    writeCards,
    writeLoggedMessage,
    writeLogicalCard,
    writeParticipants,
    writePlayerSummary,
    writeRings,
    writeStacks,
    writeState,
} from './views.js';

/**
 * Short names of the messages of the API. Every message is also a command named after its type (`/bow_card`, or
 * `/bow-card`).
 */
export const MESSAGE_ALIASES = {
    stronghold: 'choose_stronghold_province',
    reveal: 'reveal_dynasty_card',
    fill: 'fill_province',
    collect: 'collect_fate',
    'play-province': 'play_character_from_province',
    'play-hand': 'play_character_from_hand',
    attach: 'play_attachment',
    event: 'play_action_event',
    interrupt: 'play_interrupt_event',
    reaction: 'play_reaction_event',
    action: 'trigger_action_ability',
    'trigger-interrupt': 'trigger_interrupt_ability',
    'trigger-reaction': 'trigger_reaction_ability',
    pass: 'pass_opportunity',
    end: 'end_resolution',
    bid: 'bid_honor',
    'set-bid': 'set_honor_bid',
    draw: 'draw_conflict_cards',
    bow: 'bow_card',
    ready: 'ready_card',
    discard: 'discard_card',
    'discard-random': 'discard_random_card_from_hand',
    honor: 'honor_character',
    dishonor: 'dishonor_character',
    'place-fate': 'place_fate',
    'remove-fate': 'remove_fate',
    'set-skill': 'set_character_skill',
    'reset-skill': 'reset_character_skill',
    gain: 'gain_honor',
    lose: 'lose_honor',
    'gain-fate': 'gain_fate',
    'lose-fate': 'lose_fate',
    transfer: 'transfer_honor',
    conflict: 'declare_conflict',
    'pass-conflict': 'pass_conflict',
    defend: 'declare_defenders',
    'no-defenders': 'declare_no_defenders',
    move: 'move_to_conflict',
    home: 'move_home',
    skill: 'declare_conflict_skill',
    strength: 'declare_province_strength',
    glory: 'declare_glory_count',
    favor: 'claim_imperial_favor',
    duel: 'initiate_duel',
    stat: 'declare_duel_statistic',
    'end-duel': 'end_duel',
    concede: 'concede_game',
    point: 'point_at_card',
    'shuffle-conflict': 'shuffle_conflict_deck',
    'shuffle-dynasty': 'shuffle_dynasty_deck',
};

/** The commands that do not send a message of the game, by group. */
function staticCommands() {
    return [
        // account
        {
            group: 'account', name: 'login', usage: '<token>', help: 'Logs in with an access token (kept by this tab only).',
            async run(ctx, args, block) {
                expect(args, 1, this);
                await ctx.login(args[0], block);
            },
        },
        {
            group: 'account', name: 'as', usage: '<user>', help: 'Logs in as a development user of the fixtures (john, jane, harry, sally): /login <user>-dev-token.',
            async run(ctx, args, block) {
                expect(args, 1, this);
                await ctx.login(`${args[0]}-dev-token`, block);
            },
        },
        {
            group: 'account', name: 'logout', usage: '', help: 'Forgets the token of this tab.',
            run(ctx, args, block) {
                ctx.logout();
                block.success('Logged out.');
            },
        },
        {
            group: 'account', name: 'whoami', usage: '', help: 'The user and the current game.',
            run(ctx, args, block) {
                block.line(`user ${ctx.userId ?? '(not logged in)'}`);
                block.line(`game ${ctx.gameId ?? '(none)'}${ctx.game ? `, as player ${ctx.game.view.viewer_id}` : ''}`);
            },
        },
        {
            group: 'account', name: 'decks', usage: '', help: 'The decks you can play: yours and the starter decks.',
            async run(ctx, args, block) {
                const result = await ctx.api.get('/api/decks', { trace: block });
                if (result.ok) {
                    for (const deck of result.data) {
                        const count = Object.values(deck.cards).reduce((sum, quantity) => sum + quantity, 0);
                        block.line([seg(deck.id, 'ref'), seg(`  ${deck.name}`), seg(`  ${count} cards${deck.owner_id ? '' : ' · starter'}`, 'dim')]);
                    }
                }
            },
        },
        {
            group: 'account', name: 'deck', usage: '<deck id>', help: 'The cards of a deck.',
            async run(ctx, args, block) {
                expect(args, 1, this);
                const result = await ctx.api.get(`/api/decks/${encodeURIComponent(args[0])}`, { trace: block });
                if (result.ok) {
                    block.line([seg(result.data.name, 'card'), seg(`  ${result.data.id}`, 'dim')]);
                    const byType = new Map();
                    for (const [code, quantity] of Object.entries(result.data.cards)) {
                        const logical = ctx.catalogue.get(code);
                        const type = logical ? `${logical.side} ${logical.type}` : 'unknown';
                        byType.set(type, [...(byType.get(type) ?? []), `${quantity}× ${logical?.name ?? code}`]);
                    }
                    for (const [type, cards] of byType) {
                        block.line(seg(type, 'dim'));
                        cards.forEach((card) => block.line(`  ${card}`));
                    }
                }
            },
        },
        {
            group: 'account', name: 'upload-deck', aliases: ['import-deck'],
            usage: '[json]',
            help: 'Imports a deck: {"name": …, "format": "emeralddb"|"codes", "cards": {"<id>": <quantity>}} (format codes by default). Without argument, opens a JSON file.',
            async run(ctx, args, block) {
                let body;
                if (args.length === 0) {
                    const text = await ctx.pickFile();
                    if (text === null) {
                        block.dim('No file chosen.');
                        return;
                    }
                    body = JSON.parse(text);
                } else {
                    body = JSON.parse(args.join(' '));
                }
                if (body.format === undefined) {
                    body = { name: body.name ?? 'Imported deck', format: 'codes', cards: body.cards ?? body };
                }
                const result = await ctx.api.post('/api/decks', body, { trace: block });
                if (result.ok) {
                    block.success(`Deck ${result.data.id} imported: /queue ${result.data.id}`);
                }
            },
        },
        {
            group: 'account', name: 'queue', usage: '[deck id]', help: 'Joins the matchmaking queue with a deck; without argument, tells which deck you wait with.',
            async run(ctx, args, block) {
                requireUser(ctx);
                if (args.length === 0) {
                    const result = await ctx.api.get('/api/queue', { trace: block });
                    if (result.ok) block.info(`Waiting with ${result.data.deck_id}.`);
                    return;
                }
                const result = await ctx.api.post('/api/queue', { deck_id: args[0] }, { trace: block });
                if (result.ok) {
                    block.success('In the queue: the game starts as soon as another player joins.');
                }
            },
        },
        {
            group: 'account', name: 'unqueue', aliases: ['leave-queue'], usage: '', help: 'Leaves the matchmaking queue.',
            async run(ctx, args, block) {
                requireUser(ctx);
                const result = await ctx.api.delete('/api/queue', { trace: block });
                if (result.ok) block.success('Left the queue.');
            },
        },
        {
            group: 'account', name: 'games', usage: '', help: 'Your games, the last joined first.',
            async run(ctx, args, block) {
                requireUser(ctx);
                const result = await ctx.api.get('/api/self/games', { trace: block });
                if (result.ok) {
                    if (result.data.length === 0) block.dim('No game.');
                    result.data.forEach((game, index) => {
                        block.line([
                            seg(`${index + 1}. `, 'dim'),
                            seg(game.id, 'ref'),
                            seg(`  ${game.players.join(' vs ')}  v${game.version}`),
                            seg(game.ended ? '  ended' : '  in progress', game.ended ? 'dim' : 'success'),
                            seg(game.id === ctx.gameId ? '  ← current' : '', 'place'),
                        ]);
                    });
                    block.dim('/game <id or number> to play one.');
                }
            },
        },
        {
            group: 'account', name: 'game', usage: '<game id|number>', help: 'Plays one of your games (number from /games).',
            async run(ctx, args, block) {
                requireUser(ctx);
                expect(args, 1, this);
                let id = args[0];
                if (/^\d+$/.test(id)) {
                    const games = await ctx.api.get('/api/self/games');
                    id = games.data?.[Number(id) - 1]?.id ?? id;
                }
                await ctx.selectGame(id, block);
            },
        },

        // game views
        {
            group: 'view', name: 'state', aliases: ['status', 's'], usage: '', help: 'The state of the game: place, players, rings, conflict, duel, your actions.',
            run(ctx, args, block) {
                writeState(block, ctx.requireGame());
            },
        },
        {
            group: 'view', name: 'hand', aliases: ['h'], usage: '[me|opp]', help: 'The cards in hand (only a count for an opponent).',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                const player = playerArg(game, args);
                const hand = game.stacks(player, 'hand')[0];
                block.line(seg(`${game.playerName(player.id)}: ${hand?.count ?? 0} cards in hand`, 'player'));
                if (hand?.cards) {
                    writeCards(block, game, hand.cards, '(empty hand)');
                }
            },
        },
        {
            group: 'view', name: 'provinces', aliases: ['p'], usage: '[me|opp]', help: 'The provinces and the dynasty cards on them (both players by default).',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                writeStacks(block, game, playersArg(game, args), ['stronghold_province', 'regular_province']);
            },
        },
        {
            group: 'view', name: 'board', aliases: ['b', 'play'], usage: '[me|opp]', help: 'The cards in play: each character with its attachments (both players by default).',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                writeStacks(block, game, playersArg(game, args), ['in_play'], { emptyText: 'no card in play' });
            },
        },
        {
            group: 'view', name: 'discards', aliases: ['d'], usage: '[me|opp]', help: 'The discard piles.',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                writeStacks(block, game, playersArg(game, args), ['dynasty_discard_pile', 'conflict_discard_pile']);
            },
        },
        {
            group: 'view', name: 'stacks', usage: '[me|opp]', help: 'Every stack, with its count and its cards when you see them.',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                writeStacks(block, game, playersArg(game, args), Object.keys(STACK_LABELS));
            },
        },
        {
            group: 'view', name: 'me', usage: '', help: 'Your honor, fate, bids and counts.',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                writePlayerSummary(block, game, game.me);
            },
        },
        {
            group: 'view', name: 'opp', aliases: ['opponent'], usage: '', help: 'Your opponent’s honor, fate, bids and counts.',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                game.opponents.forEach((player) => writePlayerSummary(block, game, player));
            },
        },
        {
            group: 'view', name: 'rings', usage: '', help: 'The rings: fate on them, who claimed them.',
            run(ctx, args, block) {
                writeRings(block, ctx.requireGame());
            },
        },
        {
            group: 'view', name: 'current-conflict', aliases: ['cc'], usage: '', help: 'The conflict being resolved.',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                game.view.conflict ? block.json(game.describe(game.view.conflict)) : block.dim('No conflict.');
            },
        },
        {
            group: 'view', name: 'attackers', usage: '', help: 'The attacking characters of the conflict being resolved, with their attachments.',
            run(ctx, args, block) {
                writeParticipants(block, ctx.requireGame(), 'attacker');
            },
        },
        {
            group: 'view', name: 'defenders', usage: '', help: 'The defending characters of the conflict being resolved, with their attachments.',
            run(ctx, args, block) {
                writeParticipants(block, ctx.requireGame(), 'defender');
            },
        },
        {
            group: 'view', name: 'current-duel', aliases: ['cd'], usage: '', help: 'The duel being resolved.',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                game.view.duel ? block.json(game.describe(game.view.duel)) : block.dim('No duel.');
            },
        },
        {
            group: 'view', name: 'actions', aliases: ['a'], usage: '', help: 'The messages you can send now, with their arguments.',
            run(ctx, args, block) {
                const game = ctx.requireGame();
                const step = game.view.available_actions.filter((type) => !ANYTIME_ACTIONS.has(type));
                const anytime = game.view.available_actions.filter((type) => ANYTIME_ACTIONS.has(type));
                block.line(seg('Actions of the step', 'group'));
                if (step.length === 0) {
                    block.dim('  none');
                }
                step.forEach((type) => writeMessageUsage(block, ctx, type));
                if (anytime.length > 0) {
                    block.line(seg('Effects, interrupts and reactions (accepted anytime)', 'group'));
                    anytime.forEach((type) => writeMessageUsage(block, ctx, type));
                }
            },
        },
        {
            group: 'view', name: 'log', usage: '[since]', help: 'The log of the game (from a version).',
            async run(ctx, args, block) {
                const game = ctx.requireGame();
                const since = args[0] ?? 0;
                const result = await ctx.api.get(`/api/games/${encodeURIComponent(game.view.id)}/log?since=${encodeURIComponent(since)}`, { trace: ctx.verbose ? block : null });
                if (result.ok) {
                    result.data.messages.forEach((message) => writeLoggedMessage(block, game, message));
                    if (result.data.messages.length === 0) block.dim('Nothing.');
                }
            },
        },
        {
            group: 'view', name: 'card', aliases: ['c'], usage: '<card ref|code|name>', help: 'The card as printed: a card of the game you see, or any card by code or name.',
            run(ctx, args, block) {
                expect(args, 1, this, true);
                const text = args.join(' ');
                let physical = null;
                if (ctx.game) {
                    try {
                        physical = ctx.game.findCard(text);
                    } catch (error) {
                        if (error instanceof AmbiguousError) throw error;
                    }
                }
                if (physical && physical.code === null) {
                    block.line([seg(`[${ctx.game.ref(physical.id)}] `, 'ref'), seg('face-down card: you do not know it.', 'hidden')]);
                    return;
                }
                const logical = ctx.catalogue.get(physical?.code ?? text) ?? findLogical(ctx, text);
                writeLogicalCard(block, logical, physical, ctx.game);
                ctx.showCard(logical.code);
            },
        },
        {
            group: 'view', name: 'cards', usage: '<search>', help: 'Searches the cards as printed by name, trait or text.',
            run(ctx, args, block) {
                expect(args, 1, this, true);
                const search = args.join(' ').toLowerCase();
                const found = [...ctx.catalogue.values()].filter((card) => card.name.toLowerCase().includes(search) || card.traits?.includes(search) || card.text?.toLowerCase().includes(search));
                found.slice(0, 50).forEach((card) => block.line([seg(card.code, 'ref', card.code), seg(`  ${card.name}`, 'card', card.code), seg(`  ${card.side} ${card.type} · ${card.clan ?? 'neutral'}`, 'dim')]));
                block.dim(`${found.length} cards${found.length > 50 ? ' (50 shown)' : ''}.`);
            },
        },

        // tools
        {
            group: 'tools', name: 'send', usage: '<type> [args|json]', help: 'Sends any message of the API by its type, with positional, named (key=value) or JSON arguments.',
            async run(ctx, args, block) {
                expect(args, 1, this, true);
                await sendMessage(ctx, args[0], args.slice(1), block);
            },
        },
        {
            group: 'tools', name: 'refresh', aliases: ['r'], usage: '', help: 'Reads the game again (when a Mercure notification was missed).',
            async run(ctx, args, block) {
                ctx.requireGame();
                await ctx.refresh(block);
                block.dim(`version ${ctx.game.view.version}`);
            },
        },
        {
            group: 'tools', name: 'get', usage: '<path>', help: 'GET on the API with your token, shows the raw JSON: /get /api/games/<id>.',
            async run(ctx, args, block) {
                expect(args, 1, this);
                const result = await ctx.api.get(args[0], { trace: block });
                if (result.ok) block.json(result.data);
            },
        },
        {
            group: 'tools', name: 'view', usage: '', help: 'The raw view of the current game (JSON).',
            run(ctx, args, block) {
                block.json(ctx.requireGame().view);
            },
        },
        {
            group: 'tools', name: 'verbose', usage: '[on|off]', help: 'Also shows the requests the console sends by itself (refreshes).',
            run(ctx, args, block) {
                ctx.verbose = args.length === 0 ? !ctx.verbose : ['on', 'true', '1'].includes(args[0]);
                block.info(`verbose ${ctx.verbose ? 'on' : 'off'}`);
            },
        },
        {
            group: 'tools', name: 'clear', usage: '', help: 'Clears the output.',
            run(ctx) {
                ctx.terminal.clear();
                return false;
            },
        },
        {
            group: 'tools', name: 'help', aliases: ['?'], usage: '[command]', help: 'The commands, or the details of one.',
            run(ctx, args, block) {
                writeHelp(ctx, args[0], block);
            },
        },
    ];
}

/** Every command: the static ones, then one per message of the API (by type, kebab-case type and short alias). */
export function buildCommands(ctx) {
    const commands = new Map();
    const list = staticCommands();
    const aliasesOfType = new Map();
    for (const [alias, type] of Object.entries(MESSAGE_ALIASES)) {
        aliasesOfType.set(type, [...(aliasesOfType.get(type) ?? []), alias]);
    }
    for (const type of Object.keys(ctx.actions)) {
        const kebab = type.replaceAll('_', '-');
        list.push({
            group: 'message',
            name: type,
            aliases: [...(kebab === type ? [] : [kebab]), ...(aliasesOfType.get(type) ?? [])],
            usage: usage(ctx.actions[type]),
            help: ctx.actions[type].description ?? '',
            message: type,
            async run(ctx, args, block) {
                await sendMessage(ctx, type, args, block);
            },
        });
    }
    for (const command of list) {
        for (const name of [command.name, ...(command.aliases ?? [])]) {
            if (!commands.has(name)) {
                commands.set(name, command);
            }
        }
    }

    return { commands, list };
}

async function sendMessage(ctx, type, args, block) {
    const schema = ctx.actions[type];
    if (!schema) {
        throw new Error(`Unknown message type ${type}.`);
    }
    const game = ctx.requireGame();
    let payload;
    try {
        payload = buildPayload(schema, args, game);
    } catch (error) {
        if (error instanceof UsageError || error instanceof AmbiguousError) {
            block.error(error.message);
            if (error instanceof AmbiguousError) {
                error.cards.forEach((card) => block.line([seg('  '), ...game.cardSegments(card)]));
            } else {
                writeMessageUsage(block, ctx, type);
            }
            return;
        }
        throw error;
    }
    await ctx.send(type, payload, block);
}

function writeMessageUsage(block, ctx, type) {
    const schema = ctx.actions[type];
    const alias = Object.entries(MESSAGE_ALIASES).find(([, aliased]) => aliased === type)?.[0];
    block.line([seg(`/${alias ?? type} `, 'command-name'), seg(usage(schema), 'usage'), seg(alias ? `   (${type})` : '', 'dim')]);
}

function writeHelp(ctx, name, block) {
    const { commands, list } = ctx.commands;
    if (name) {
        const command = commands.get(name.replace(/^\//, ''));
        if (!command) {
            block.error(`Unknown command ${name}.`);
            return;
        }
        block.line([seg(`/${command.name} `, 'command-name'), seg(command.usage, 'usage')]);
        if (command.aliases?.length) block.dim(`aliases: ${command.aliases.map((alias) => `/${alias}`).join(' ')}`);
        for (const paragraph of command.help.split('\n')) block.line(paragraph);
        if (command.message) {
            const schema = ctx.actions[command.message];
            for (const [property, definition] of Object.entries(schema.properties ?? {})) {
                block.line([seg(`  ${property}`, 'usage'), seg(`  ${definition.description ?? ''}`, 'dim')]);
            }
        }
        return;
    }

    const groups = { account: 'Account and matchmaking', view: 'Game views', message: 'Game messages (sent to the API)', tools: 'Tools' };
    for (const [group, title] of Object.entries(groups)) {
        block.line(seg(title, 'group'));
        for (const command of list.filter((item) => item.group === group)) {
            const shown = command.message ? (command.aliases?.find((alias) => MESSAGE_ALIASES[alias] === command.message) ?? command.name) : command.name;
            const firstLine = (command.help.split('\n')[0] ?? '').slice(0, 110);
            block.line([seg(`  /${shown} `, 'command-name'), seg(command.usage, 'usage'), seg(`  ${firstLine}`, 'dim')]);
        }
    }
    block.dim('Cards: their reference [0a19] (or a unique prefix, or part of their name); several: a,b,c. Players: me, opp or a user id (me by default).');
    block.dim('Named arguments: key=value (e.g. card_cost=2). /help <command> for the details. ↑/↓: history, Tab: completion.');
}

function requireUser(ctx) {
    if (!ctx.userId) {
        throw new Error('Log in first: /login <token> or /as <user>.');
    }
}

function expect(args, count, command, atLeast = false) {
    if (atLeast ? args.length < count : args.length !== count) {
        throw new UsageError(`Usage: /${command.name} ${command.usage}`);
    }
}

function playerArg(game, args) {
    if (args.length === 0) return game.me;
    const player = game.findPlayer(args[0]);
    if (!player) throw new UsageError(`No player matches "${args[0]}".`);
    return player;
}

function playersArg(game, args) {
    return args.length === 0 ? game.view.players : [playerArg(game, args)];
}

function findLogical(ctx, text) {
    const lower = text.toLowerCase();
    const found = [...ctx.catalogue.values()].filter((card) => card.name.toLowerCase().includes(lower));
    const exact = found.find((card) => card.name.toLowerCase() === lower);
    if (exact) return exact;
    if (found.length === 1) return found[0];
    if (found.length === 0) throw new Error(`No card matches "${text}".`);
    throw new Error(`"${text}" matches ${found.length} cards: ${found.slice(0, 10).map((card) => card.name).join(', ')}…`);
}
