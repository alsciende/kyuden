/**
 * Splits a command line into its name and its arguments: words separated by spaces, except inside quotes and inside
 * a JSON object or array (`/upload-deck {"name": "My deck", ...}` gives a single argument).
 */
export function parseCommandLine(line) {
    const tokens = tokenize(line.trim());
    const name = (tokens.shift() ?? '').replace(/^\//, '').toLowerCase();

    return { name, args: tokens };
}

export function tokenize(text) {
    const tokens = [];
    let current = '';
    let started = false;
    let quote = null;
    let depth = 0;
    let jsonQuote = false;

    for (let i = 0; i < text.length; i++) {
        const char = text[i];

        if (depth > 0) {
            current += char;
            if (jsonQuote) {
                if (char === '\\') {
                    current += text[++i] ?? '';
                } else if (char === '"') {
                    jsonQuote = false;
                }
            } else if (char === '"') {
                jsonQuote = true;
            } else if (char === '{' || char === '[') {
                depth++;
            } else if (char === '}' || char === ']') {
                depth--;
            }
            continue;
        }

        if (quote !== null) {
            if (char === quote) {
                quote = null;
            } else {
                current += char;
            }
            continue;
        }

        if (/\s/.test(char)) {
            if (started) {
                tokens.push(current);
                current = '';
                started = false;
            }
            continue;
        }

        started = true;
        if ((char === '"' || char === "'") && current === '') {
            quote = char;
        } else if ((char === '{' || char === '[') && current === '') {
            depth = 1;
            current += char;
        } else {
            current += char;
        }
    }
    if (quote !== null || depth > 0) {
        throw new Error('Unbalanced quotes or brackets.');
    }
    if (started) {
        tokens.push(current);
    }

    return tokens;
}
