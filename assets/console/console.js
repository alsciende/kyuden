import { Api } from './api.js';
import { GameModel } from './game.js';
import { Mercure } from './mercure.js';
import { seg } from './terminal.js';
import { writeAvailableActions, writeLoggedMessage } from './views.js';

const TOKEN_KEY = 'l5r.token';
const GAME_KEY = 'l5r.game';

/**
 * The session of the console: the user (their token is kept by the tab, so that two tabs can play the two players),
 * the current game and its last view, the cards as printed and the schemas of the messages. It follows the
 * notifications of the hub: when the current game changes, it reads the view again and writes the new entries of the
 * log.
 */
export class Console {
    constructor(terminal, root, onChange) {
        this.terminal = terminal;
        this.onChange = onChange;
        this.api = new Api(this);
        this.token = storage(() => sessionStorage.getItem(TOKEN_KEY));
        this.userId = null;
        this.gameId = null;
        this.game = null;
        this.logVersion = 0;
        this.catalogue = new Map();
        this.actions = {};
        this.verbose = false;
        this.sending = false;
        this.refreshing = Promise.resolve();
        this.mercure = new Mercure(
            root.dataset.mercureUrl,
            this.api,
            () => this.onChange(),
            (data) => this.onNotification(data),
        );
        this.cardPanel = null;
    }

    /** Shows a card as printed in the card panel, by code. */
    showCard(code) {
        this.cardPanel?.show(this.catalogue.get(code));
    }

    /** Reads what depends on no user (cards, schemas of the messages), then logs in again with the token of the tab. */
    async init() {
        const block = this.terminal.block('L5R game engine console', 'system');
        block.info('Type /help for the commands. Log in with /login <token>, or /as <user> with a development user (john, jane, harry, sally).');
        // cacheable for an hour: the console revalidates them, so that it follows the changes of the engine
        const [cards, actions] = await Promise.all([
            this.api.get('/api/cards', { cache: 'no-cache' }),
            this.api.get('/api/actions', { cache: 'no-cache' }),
        ]);
        if (cards.ok) {
            this.catalogue = new Map(cards.data.map((card) => [card.code, card]));
        } else {
            block.error(`Cannot read the cards (${cards.status}).`);
        }
        if (actions.ok) {
            this.actions = actions.data;
        } else {
            block.error(`Cannot read the actions (${actions.status}).`);
        }
        block.dim(`${this.catalogue.size} cards, ${Object.keys(this.actions).length} actions loaded.`);

        if (this.token) {
            await this.login(this.token, block, storage(() => sessionStorage.getItem(GAME_KEY)));
        }
        this.onChange();
    }

    async login(token, block, preferredGameId = null) {
        this.mercure.logout();
        this.token = token;
        const self = await this.api.get('/api/self', { trace: block });
        if (!self.ok) {
            this.logout();
            block.error('Unknown token.');
            return false;
        }
        this.userId = self.data.user_id;
        storage(() => sessionStorage.setItem(TOKEN_KEY, token));
        block.success(`Logged in as ${this.userId}.`);
        this.subscribe();

        const games = await this.api.get('/api/self/games', { trace: this.verbose ? block : null });
        // a game at version 0 has never been created through the bus (fixtures)
        const inProgress = games.ok ? games.data.filter((game) => !game.ended && game.version > 0) : [];
        const preferred = games.ok ? games.data.find((game) => game.id === preferredGameId) : undefined;
        const chosen = preferred ?? inProgress[0];
        if (chosen) {
            await this.selectGame(chosen.id, block);
        } else {
            block.dim('No game in progress: /queue <deck> to find one (/decks lists your decks).');
        }
        const queue = await this.api.get('/api/queue');
        if (queue.ok) {
            block.info(`Waiting in the queue with ${queue.data.deck_id}.`);
        }

        return true;
    }

    logout() {
        this.mercure.logout();
        this.token = null;
        this.userId = null;
        this.gameId = null;
        this.game = null;
        storage(() => sessionStorage.removeItem(TOKEN_KEY));
        storage(() => sessionStorage.removeItem(GAME_KEY));
        this.onChange();
    }

    async selectGame(gameId, block) {
        this.gameId = gameId;
        this.game = null;
        this.logVersion = 0;
        storage(() => sessionStorage.setItem(GAME_KEY, gameId));
        this.subscribe();
        const view = await this.api.get(`/api/games/${encodeURIComponent(gameId)}`, { trace: block });
        if (!view.ok) {
            this.gameId = null;
            storage(() => sessionStorage.removeItem(GAME_KEY));
            this.subscribe();
            block.error('Cannot read this game.');
            this.onChange();
            return false;
        }
        this.game = new GameModel(view.data, this.catalogue);
        block.success(`Playing game ${gameId} as ${this.game.playerName(this.game.view.viewer_id)} against ${this.game.opponents.map((player) => player.user_id).join(', ')}.`);

        const log = await this.api.get(`/api/games/${encodeURIComponent(gameId)}/log`);
        if (log.ok) {
            const recent = log.data.messages.slice(-5);
            if (log.data.messages.length > recent.length) {
                block.dim(`… ${log.data.messages.length - recent.length} earlier messages (/log)`);
            }
            for (const message of recent) {
                writeLoggedMessage(block, this.game, message);
            }
            this.logVersion = log.data.version;
        }
        block.line([seg('place      ', 'dim'), seg(this.game.view.place ?? '—', 'place')]);
        writeAvailableActions(block, this.game.view);
        this.onChange();

        return true;
    }

    subscribe() {
        const topics = [];
        if (this.userId) topics.push(`/users/${this.userId}`);
        if (this.gameId) topics.push(`/games/${this.gameId}`);
        this.mercure.subscribe(topics);
    }

    requireGame() {
        if (!this.userId) {
            throw new Error('Log in first: /login <token> or /as <user>.');
        }
        if (!this.game) {
            throw new Error('No current game: /games lists yours, /game <id> picks one, /queue <deck> finds one.');
        }

        return this.game;
    }

    /** Sends a message of the API on the current game, then writes what it changed. */
    async send(type, payload, block) {
        const game = this.requireGame();
        this.sending = true;
        try {
            const result = await this.api.post(`/api/games/${encodeURIComponent(game.view.id)}/messages`, { type, payload }, { trace: block });
            if (result.ok) {
                block.success(`${type} handled, version ${result.version}.`);
            }
            await this.refresh(block);

            return result;
        } finally {
            this.sending = false;
        }
    }

    /**
     * Reads the view of the current game again and writes the new entries of its log, in the block given or in a new
     * one. The refreshes are serialized, so that an entry is written once.
     */
    refresh(block = null) {
        this.refreshing = this.refreshing.then(() => this.doRefresh(block)).catch((error) => {
            (block ?? this.terminal.block('refresh', 'error')).error(error.message);
        });

        return this.refreshing;
    }

    async doRefresh(block) {
        if (!this.gameId || !this.game) {
            return;
        }
        const previous = this.game.view;
        const view = await this.api.get(`/api/games/${encodeURIComponent(this.gameId)}`, {
            headers: { 'If-None-Match': `"${previous.version}"` },
            trace: this.verbose ? block : null,
        });
        if (view.ok) {
            this.game = new GameModel(view.data, this.catalogue);
        }
        if (this.game.view.version <= this.logVersion) {
            this.onChange();
            return;
        }

        const log = await this.api.get(`/api/games/${encodeURIComponent(this.gameId)}/log?since=${this.logVersion}`, { trace: this.verbose ? block : null });
        if (log.ok && log.data.messages.length > 0) {
            const target = block ?? this.terminal.block(`game updated · v${log.data.version}`, 'game');
            for (const message of log.data.messages) {
                writeLoggedMessage(target, this.game, message);
            }
            this.logVersion = log.data.version;
            const view = this.game.view;
            if (view.place !== previous.place) {
                target.line([seg('place      ', 'dim'), seg(`${previous.place} → `, 'dim'), seg(view.place ?? '—', 'place')]);
            }
            if (view.active_player_ids.includes(view.viewer_id)) {
                writeAvailableActions(target, view);
            }
            if (view.winner_id) {
                target.success(`${this.game.playerName(view.winner_id)} wins (${view.victory_condition}).`);
            }
        }
        this.onChange();
    }

    onNotification(data) {
        if (data.event === 'game_updated' && data.id === this.gameId) {
            if (!this.sending && (this.game === null || data.version > this.game.view.version)) {
                this.refresh();
            }
            return;
        }
        // a game of the user has started: the queue has matched them, or they play the house bot
        if (data.event === 'game_started' && data.id !== this.gameId) {
            const block = this.terminal.block('game started', 'game');
            block.success(`Your game ${data.id} has started.`);
            if (!this.game || this.game.view.winner_id) {
                this.selectGame(data.id, block);
            } else {
                block.dim(`/game ${data.id} to play it.`);
            }
        }
    }
}

/** sessionStorage can be missing or throw (private window, blocked site data): the console then works without it. */
function storage(callback) {
    try {
        return callback();
    } catch {
        return null;
    }
}
