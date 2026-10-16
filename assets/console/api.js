/**
 * The HTTP API of the engine, called with the access token of the session. When a block is given, the request and
 * the response are written in it, so that the user sees what the console sends and what the server answers.
 */
export class Api {
    constructor(session) {
        this.session = session;
    }

    async request(method, path, { body, headers = {}, trace = null, cache = 'default' } = {}) {
        const requestHeaders = { Accept: 'application/json', ...headers };
        if (this.session.token) {
            requestHeaders.Authorization = `Bearer ${this.session.token}`;
        }
        if (body !== undefined) {
            requestHeaders['Content-Type'] = 'application/json';
        }

        trace?.line(`→ ${method} ${path}`, 'request');
        if (body !== undefined) {
            trace?.line(JSON.stringify(body), 'request');
        }

        let response;
        try {
            response = await fetch(path, {
                method,
                headers: requestHeaders,
                cache,
                body: body === undefined ? undefined : JSON.stringify(body),
            });
        } catch (error) {
            trace?.error(`✗ ${error.message}`);
            throw error;
        }

        const text = await response.text();
        let data = null;
        if (text !== '') {
            try {
                data = JSON.parse(text);
            } catch {
                data = text;
            }
        }
        const etag = response.headers.get('ETag');
        const result = {
            status: response.status,
            ok: response.ok,
            notModified: response.status === 304,
            version: etag === null ? null : Number(etag.replaceAll('"', '').replace(/^W\//, '')),
            data,
        };

        if (trace) {
            const etagText = etag === null ? '' : `  ETag ${etag}`;
            trace.line(`← ${response.status} ${response.statusText}${etagText}`, response.ok || result.notModified ? 'response' : 'error');
            if (!response.ok && data !== null) {
                trace.json(data, 'json problem');
            }
        }

        return result;
    }

    get(path, options = {}) {
        return this.request('GET', path, options);
    }

    post(path, body, options = {}) {
        return this.request('POST', path, { ...options, body });
    }

    delete(path, options = {}) {
        return this.request('DELETE', path, options);
    }
}
