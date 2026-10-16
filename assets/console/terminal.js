/**
 * The output of the console: blocks of lines, the newest on top, right under the command line. A line is a list of
 * segments, each a text with an optional style; everything is written as text, never as HTML.
 */

/**
 * A segment of a line: `seg('Shiro Nishiyama', 'card')`. A segment naming a card carries its code, so that the card
 * panel shows its image when the pointer is over it.
 */
export function seg(text, style = null, code = null) {
    return { text: String(text), style, code };
}

export class Block {
    constructor(element) {
        this.element = element;
    }

    /** Adds a line: a string, a segment or a list of them. */
    line(content, style = null) {
        const line = document.createElement('div');
        line.className = 'line' + (style ? ` ${style}` : '');
        for (const part of Array.isArray(content) ? content : [content]) {
            const segment = typeof part === 'object' && part !== null ? part : seg(part ?? '');
            const span = document.createElement('span');
            if (segment.style) {
                span.className = segment.style;
            }
            if (segment.code) {
                span.dataset.code = segment.code;
            }
            span.textContent = segment.text;
            line.append(span);
        }
        this.element.append(line);

        return this;
    }

    info(text) { return this.line(text, 'info'); }

    error(text) { return this.line(text, 'error'); }

    success(text) { return this.line(text, 'success'); }

    dim(text) { return this.line(text, 'dim'); }

    /** A value shown as indented JSON. */
    json(value, style = 'json') {
        const pre = document.createElement('pre');
        pre.className = style;
        pre.textContent = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
        this.element.append(pre);

        return this;
    }
}

export class Terminal {
    constructor(output) {
        this.output = output;
    }

    /** Opens a new block on top of the output, titled by the command or the source that writes it. */
    block(title, style = 'command') {
        const element = document.createElement('section');
        element.className = `block ${style}`;
        const header = document.createElement('div');
        header.className = 'block-title';
        const time = document.createElement('time');
        time.textContent = new Date().toLocaleTimeString();
        const text = document.createElement('span');
        text.textContent = title;
        header.append(time, text);
        element.append(header);
        this.output.prepend(element);

        return new Block(element);
    }

    clear() {
        this.output.replaceChildren();
    }
}
