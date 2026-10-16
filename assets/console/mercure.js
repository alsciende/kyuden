/**
 * The subscription to the Mercure hub: the topics of the user (`/users/{id}`) and of the current game (`/games/{id}`:
 * it has changed). Every update received is handed to the console; the state of the connection is shown in the
 * status bar.
 */
export class Mercure {
    constructor(hubUrl, onStatus, onUpdate) {
        this.hubUrl = hubUrl;
        this.onStatus = onStatus;
        this.onUpdate = onUpdate;
        this.source = null;
        this.topics = [];
        this.state = 'off';
    }

    subscribe(topics) {
        const unique = [...new Set(topics)].sort();
        if (unique.join('|') === this.topics.join('|') && this.source !== null) {
            return;
        }
        this.close();
        this.topics = unique;
        if (unique.length === 0) {
            return;
        }

        const url = new URL(this.hubUrl, window.location.href);
        for (const topic of unique) {
            url.searchParams.append('topic', topic);
        }
        this.setState('connecting');
        this.source = new EventSource(url);
        this.source.onopen = () => this.setState('on');
        this.source.onerror = () => this.setState('error');
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

    close() {
        this.source?.close();
        this.source = null;
        this.topics = [];
        this.setState('off');
    }

    setState(state) {
        this.state = state;
        this.onStatus();
    }
}
