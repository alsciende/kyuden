import { buildCommands } from './commands.js';
import { Console } from './console.js';
import { ActionsPanel, CardPanel } from './panels.js';
import { parseCommandLine } from './parse.js';
import { Terminal } from './terminal.js';

const HISTORY_KEY = 'l5r.history';
const HISTORY_SIZE = 200;

/**
 * The development console: a command line (`/help`), the output of the commands under it, and the notifications of
 * the Mercure hub beside them. The commands are interpreted here and call the HTTP API: nothing typed is run as code.
 */
export async function start(root) {
    const input = root.querySelector('#command');
    const form = root.querySelector('#prompt');
    const status = root.querySelector('#status');
    const fileInput = root.querySelector('#deck-file');
    const output = root.querySelector('#output');
    const terminal = new Terminal(output);
    const actionsPanel = new ActionsPanel(root.querySelector('#actions-panel'), (text) => {
        input.value = text;
        input.focus();
    });
    const ctx = new Console(terminal, root, () => {
        renderStatus(status, ctx);
        actionsPanel.render(ctx);
    });
    ctx.cardPanel = new CardPanel(root.querySelector('#card-panel'));
    ctx.commands = buildCommands(ctx);
    output.addEventListener('mouseover', (event) => {
        const code = event.target.closest?.('[data-code]')?.dataset.code;
        if (code) {
            ctx.showCard(code);
        }
    });
    ctx.pickFile = () => pickFile(fileInput);

    const history = loadHistory();
    let historyIndex = history.length;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const line = input.value.trim();
        input.value = '';
        if (line === '') {
            return;
        }
        // a token is not kept in the history
        if (history[history.length - 1] !== line && !/^\/?login\b/.test(line)) {
            history.push(line);
            history.splice(0, Math.max(0, history.length - HISTORY_SIZE));
            saveHistory(history);
        }
        historyIndex = history.length;
        await execute(ctx, terminal, line);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowUp' && historyIndex > 0) {
            event.preventDefault();
            input.value = history[--historyIndex];
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            historyIndex = Math.min(history.length, historyIndex + 1);
            input.value = history[historyIndex] ?? '';
        } else if (event.key === 'Tab') {
            event.preventDefault();
            complete(ctx, terminal, input);
        }
    });
    root.querySelector('#terminal').addEventListener('click', (event) => {
        if (window.getSelection()?.toString() === '' && event.target.closest('#output') === null) {
            input.focus();
        }
    });

    try {
        await ctx.init();
        ctx.commands = buildCommands(ctx);
    } catch (error) {
        terminal.block('init', 'error').error(error.message);
    }
    renderStatus(status, ctx);
    actionsPanel.render(ctx);
    input.focus();
}

async function execute(ctx, terminal, line) {
    let parsed;
    try {
        parsed = parseCommandLine(line.startsWith('/') ? line : `/${line}`);
    } catch (error) {
        terminal.block(line).error(error.message);
        return;
    }
    const command = ctx.commands.commands.get(parsed.name);
    if (!command) {
        terminal.block(line).error(`Unknown command /${parsed.name}: /help lists the commands.`);
        return;
    }
    if (command.name === 'clear') {
        command.run(ctx, parsed.args);
        return;
    }
    const block = terminal.block(line);
    try {
        await command.run(ctx, parsed.args, block);
    } catch (error) {
        block.error(error.message);
    }
}

function complete(ctx, terminal, input) {
    const value = input.value;
    if (value.includes(' ')) {
        return;
    }
    const prefix = value.replace(/^\//, '');
    const names = [...ctx.commands.commands.keys()].filter((name) => name.startsWith(prefix)).sort();
    if (names.length === 1) {
        input.value = `/${names[0]} `;
    } else if (names.length > 1) {
        const common = names.reduce((shared, name) => {
            let length = 0;
            while (length < shared.length && shared[length] === name[length]) length++;
            return shared.slice(0, length);
        });
        input.value = `/${common}`;
        terminal.block(`completions of /${prefix}`, 'system').dim(names.map((name) => `/${name}`).join('  '));
    }
}

function renderStatus(status, ctx) {
    const parts = [];
    parts.push(item('user', ctx.userId ?? 'not logged in', ctx.userId ? null : 'warn'));
    parts.push(item('mercure', ctx.mercure.state, `mercure-${ctx.mercure.state}`));
    if (ctx.game) {
        const view = ctx.game.view;
        const me = ctx.game.me;
        const active = view.active_player_ids.includes(view.viewer_id);
        parts.push(item('game', view.id.slice(0, 8)));
        parts.push(item('v', String(view.version)));
        parts.push(item('place', view.place ?? '—', 'place'));
        parts.push(item('active', view.active_player_ids.map((id) => ctx.game.playerName(id)).join(', ') || '—', active ? 'success' : null));
        parts.push(item('honor', `${me.honor ?? '?'}`));
        parts.push(item('fate', `${me.fate}`));
        for (const opponent of ctx.game.opponents) {
            parts.push(item(opponent.user_id, `honor ${opponent.honor ?? '?'} · fate ${opponent.fate}`));
        }
        if (view.winner_id) {
            parts.push(item('winner', ctx.game.playerName(view.winner_id), 'success'));
        }
    } else if (ctx.gameId) {
        parts.push(item('game', ctx.gameId.slice(0, 8)));
    }
    status.replaceChildren(...parts);
}

function item(label, value, style = null) {
    const element = document.createElement('span');
    element.className = 'status-item';
    const labelElement = document.createElement('span');
    labelElement.className = 'label';
    labelElement.textContent = label;
    const valueElement = document.createElement('span');
    valueElement.className = `value${style ? ` ${style}` : ''}`;
    valueElement.textContent = value;
    element.append(labelElement, valueElement);

    return element;
}

function pickFile(fileInput) {
    return new Promise((resolve) => {
        fileInput.value = '';
        fileInput.onchange = async () => {
            const file = fileInput.files?.[0];
            resolve(file ? await file.text() : null);
        };
        fileInput.oncancel = () => resolve(null);
        fileInput.click();
    });
}

function loadHistory() {
    try {
        const history = JSON.parse(localStorage.getItem(HISTORY_KEY) ?? '[]');
        return Array.isArray(history) ? history.filter((line) => typeof line === 'string') : [];
    } catch {
        return [];
    }
}

function saveHistory(history) {
    try {
        localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
    } catch {
        // the history is a convenience
    }
}
