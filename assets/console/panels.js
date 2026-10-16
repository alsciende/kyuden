import { MESSAGE_ALIASES } from './commands.js';
import { usage } from './payload.js';
import { ANYTIME_ACTIONS } from './views.js';

/**
 * The side column: the messages the user can send now (from the last view of the game), and the image of a card.
 */
export class ActionsPanel {
    constructor(element, onPick) {
        this.element = element;
        this.onPick = onPick;
        this.anytimeOpen = false;
    }

    render(ctx) {
        const game = ctx.game;
        const children = [heading('Actions')];
        if (!ctx.userId) {
            children.push(note('/as <user> or /login <token>'));
        } else if (!game) {
            children.push(note('No current game: /queue <deck>, /games'));
        } else if (game.view.winner_id) {
            children.push(note(`The game has ended: ${game.playerName(game.view.winner_id)} wins.`));
        } else {
            const available = game.view.available_actions;
            const step = available.filter((type) => !ANYTIME_ACTIONS.has(type));
            const anytime = available.filter((type) => ANYTIME_ACTIONS.has(type));
            const place = document.createElement('div');
            place.className = 'panel-place';
            place.textContent = game.view.place ?? '—';
            children.push(place);
            children.push(step.length > 0 ? this.list(ctx, step) : note('No action of the step for you now.'));

            if (anytime.length > 0) {
                const details = document.createElement('details');
                details.open = this.anytimeOpen;
                details.addEventListener('toggle', () => {
                    this.anytimeOpen = details.open;
                });
                const summary = document.createElement('summary');
                summary.textContent = `Accepted anytime (${anytime.length})`;
                details.append(summary, this.list(ctx, anytime));
                children.push(details);
            }
        }
        this.element.replaceChildren(...children);
    }

    list(ctx, types) {
        const list = document.createElement('ul');
        for (const type of types) {
            const command = Object.entries(MESSAGE_ALIASES).find(([, aliased]) => aliased === type)?.[0] ?? type;
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.title = ctx.actions[type]?.description ?? type;
            const name = document.createElement('span');
            name.className = 'command-name';
            name.textContent = `/${command}`;
            const args = document.createElement('span');
            args.className = 'usage';
            args.textContent = ctx.actions[type] ? ` ${usage(ctx.actions[type])}` : '';
            button.append(name, args);
            button.addEventListener('click', () => this.onPick(`/${command} `));
            item.append(button);
            list.append(item);
        }

        return list;
    }
}

export class CardPanel {
    constructor(element) {
        this.element = element;
        this.code = null;
        this.render(null);
    }

    show(logical) {
        if (logical === null || logical === undefined || logical.code === this.code) {
            return;
        }
        this.code = logical.code;
        this.render(logical);
    }

    render(logical) {
        if (logical === null) {
            this.element.replaceChildren(note('Point at a card, or /card <card>, to see it here.'));
            return;
        }
        const caption = document.createElement('div');
        caption.className = 'card-caption';
        caption.textContent = logical.name;
        if (!logical.image_url) {
            this.element.replaceChildren(caption, note('No image for this card.'));
            return;
        }
        const image = document.createElement('img');
        image.alt = logical.name;
        image.src = logical.image_url;
        image.referrerPolicy = 'no-referrer';
        image.addEventListener('error', () => {
            image.replaceWith(note('The image cannot be loaded.'));
        });
        this.element.replaceChildren(image, caption);
    }
}

function heading(text) {
    const element = document.createElement('h2');
    element.textContent = text;

    return element;
}

function note(text) {
    const element = document.createElement('div');
    element.className = 'panel-note';
    element.textContent = text;

    return element;
}
