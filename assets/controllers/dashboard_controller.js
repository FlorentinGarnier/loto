import { Controller } from '@hotwired/stimulus';
import { adminCsrfToken } from '../csrf.js';

/**
 * Écran d'animation. L'affichage suit toujours l'état renvoyé par le serveur
 * (réponse au tirage, flux Mercure, resynchronisation) : une case ne change
 * d'aspect que si le tirage a été accepté.
 */
export default class extends Controller {
    static targets = [
        'potentials', 'potentialsContainer', 'lastNumber', 'grid', 'draws', 'progression',
        'freezeBanner', 'unfreeze', 'offline', 'gridMessage', 'demarque'
    ];

    static values = {
        eventId: Number,
        gameId: Number,
        mercureUrl: String,
        toggleUrl: String,
        potentialsFragmentUrl: String,
        stateUrl: String
    }

    connect() {
        this.setupGridToggle();
        this.setupMercure();
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }

    setupGridToggle() {
        if (!this.hasGridTarget) return;

        this.gridTarget.addEventListener('click', (e) => {
            const btn = e.target.closest('button.cell');
            if (btn) this.toggle(btn);
        });
    }

    async toggle(btn) {
        // Un second clic pendant l'envoi annulerait le tirage
        if (btn.getAttribute('aria-busy') === 'true') return;

        const num = btn.dataset.number;
        const url = this.toggleUrlValue.replace('/0', '/' + num);
        btn.setAttribute('aria-busy', 'true');

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: new URLSearchParams({ _token: adminCsrfToken() })
            });
            const isJson = res.headers.get('content-type')?.includes('json');
            const data = isJson ? await res.json() : null;

            if (data?.state) this.applyState(data.state);

            if (res.ok && data?.state) {
                this.showGridMessage('');
            } else if (data?.message) {
                this.showGridMessage(data.message);
            } else {
                // Réponse inattendue : session expirée ou jeton périmé
                this.showGridMessage(`Le numéro ${num} n'a pas été enregistré. Rechargez la page, puis recommencez.`);
            }
        } catch (err) {
            this.showGridMessage(`Connexion perdue : le numéro ${num} n'a pas été enregistré. Réessayez.`);
        } finally {
            btn.removeAttribute('aria-busy');
        }
    }

    setupMercure() {
        if (!this.hasOfflineTarget || !this.eventIdValue || !this.mercureUrlValue) return;

        const topic = encodeURIComponent(`/events/${this.eventIdValue}/games/${this.gameIdValue}/state`);
        this.eventSource = new EventSource(`${this.mercureUrlValue}?topic=${topic}`);

        // À chaque (re)connexion, on relit l'état : le flux ne rejoue pas ce qui a été manqué
        this.eventSource.onopen = () => {
            this.offlineTarget.hidden = true;
            this.resync();
        };

        this.eventSource.onmessage = (e) => {
            try {
                this.applyState(JSON.parse(e.data));
            } catch (err) {
                console.error('Message temps réel illisible :', err);
            }
        };

        // Le navigateur retente seul la connexion
        this.eventSource.onerror = () => {
            this.offlineTarget.hidden = false;
        };
    }

    async resync() {
        if (!this.stateUrlValue) return;

        try {
            const res = await fetch(this.stateUrlValue, { headers: { 'Accept': 'application/json' } });
            if (res.ok && res.headers.get('content-type')?.includes('json')) {
                this.applyState(await res.json());
            }
        } catch (err) {
            // Sans réseau, le bandeau de coupure s'affichera par le flux temps réel
        }
    }

    /**
     * state : { gameId, draws: [numéros dans l'ordre du tirage], potentialsCount, isFrozen, … }
     */
    applyState(state) {
        if (!state || state.gameId !== this.gameIdValue) return;

        const draws = state.draws || [];
        const drawn = new Set(draws);

        if (this.hasGridTarget) {
            this.gridTarget.querySelectorAll('button.cell').forEach((cell) => {
                cell.classList.toggle('is-drawn', drawn.has(Number(cell.dataset.number)));
            });
        }
        if (this.hasDrawsTarget) this.drawsTarget.textContent = draws.length;
        if (this.hasProgressionTarget) this.progressionTarget.textContent = `${draws.length}/90`;
        if (this.hasLastNumberTarget) {
            this.lastNumberTarget.textContent = draws.length > 0 ? draws[draws.length - 1] : '—';
            this.lastNumberTarget.classList.toggle('is-set', draws.length > 0);
        }
        if (this.hasPotentialsTarget && state.potentialsCount !== undefined) {
            this.potentialsTarget.textContent = state.potentialsCount;
        }

        // La confirmation de la démarque annonce le nombre réel de numéros qui seront effacés
        if (this.hasDemarqueTarget) {
            this.demarqueTarget.dataset.confirmActionMessageValue =
                this.demarqueTarget.dataset.messageTemplate.replace('%n%', draws.length);
        }

        const frozen = state.isFrozen === true;
        if (this.hasFreezeBannerTarget) this.freezeBannerTarget.hidden = !frozen;
        if (this.hasUnfreezeTarget) this.unfreezeTarget.hidden = !frozen;
        // Le message de refus « partie gelée » n'a plus lieu d'être une fois la partie dégelée
        if (!frozen && this.wasFrozen) this.showGridMessage('');
        this.wasFrozen = frozen;

        // La liste des potentiels n'est relue que si l'état a changé :
        // la réponse au tirage et le flux temps réel annoncent souvent le même
        const signature = JSON.stringify([draws, frozen, state.potentialsCount, state.hasSystemWinner]);
        if (signature !== this.lastSignature) {
            this.lastSignature = signature;
            this.refreshPotentials();
        }
    }

    async refreshPotentials() {
        if (!this.hasPotentialsContainerTarget || !this.potentialsFragmentUrlValue) return;

        try {
            const res = await fetch(this.potentialsFragmentUrlValue);
            // Une redirection signifie une session expirée : on n'injecte pas la page de connexion
            if (res.ok && !res.redirected) {
                this.potentialsContainerTarget.innerHTML = await res.text();
            }
        } catch (err) {
            // La liste sera relue au prochain changement d'état
        }
    }

    showGridMessage(message) {
        if (!this.hasGridMessageTarget) return;

        this.gridMessageTarget.textContent = message;
        this.gridMessageTarget.hidden = message === '';
    }
}
