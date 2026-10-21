/**
 * The subscription to the Mercure hub: the topics of the user (`/users/{id}`) and of the current game (`/games/{id}`:
 * it has changed). The topics are private: the subscriber token of the user (`GET /api/self/subscription`) is sent as
 * the `authorization` query parameter, and a new one is asked for once the hub closes the connection (expired token).
 * Every update received is handed to the console; the state of the connection is shown in the status bar.
 */
export class Mercure {
    constructor(hubUrl, api, onStatus, onUpdate) {
        this.hubUrl = hubUrl;
        this.api = api;
        this.onStatus = onStatus;
        this.onUpdate = onUpdate;
        this.source = null;
        this.topics = [];
        this.token = null;
        this.retry = null;
        this.state = 'off';
    }

    subscribe(topics) {
        const unique = [...new Set(topics)].sort();
        if (unique.join('|') === this.topics.join('|') && (this.source !== null || this.retry !== null)) {
            return;
        }
        this.close();
        this.topics = unique;
        if (unique.length > 0) {
            this.connect();
        }
    }

    async connect() {
        const topics = this.topics;
        this.setState('connecting');
        if (this.token === null) {
            const subscription = await this.api.get('/api/self/subscription');
            if (topics !== this.topics) {
                return;
            }
            if (!subscription.ok) {
                this.setState('error');
                return;
            }
            this.token = subscription.data.token;
        }

        const url = new URL(this.hubUrl, window.location.href);
        for (const topic of topics) {
            url.searchParams.append('topic', topic);
        }
        url.searchParams.set('authorization', this.token);
        this.source = new EventSource(url);
        this.source.onopen = () => this.setState('on');
        this.source.onerror = () => {
            this.setState('error');
            // the browser reconnects by itself, unless the hub refused the token: ask for a new one
            if (this.source?.readyState === EventSource.CLOSED) {
                this.source = null;
                this.token = null;
                this.retry = setTimeout(() => {
                    this.retry = null;
                    this.connect();
                }, 2000);
            }
        };
        this.source.onmessage = (message) => {
            let data;
            try {
                data = JSON.parse(message.data);
            } catch {
                return;
            }
            if (typeof data === 'object' && data !== null) {
                this.onUpdate(data);
            }
        };
    }

    /** Closes the subscription; a new user gets a new token. */
    close() {
        this.source?.close();
        this.source = null;
        clearTimeout(this.retry);
        this.retry = null;
        this.topics = [];
        this.setState('off');
    }

    logout() {
        this.close();
        this.token = null;
    }

    setState(state) {
        this.state = state;
        this.onStatus();
    }
}
