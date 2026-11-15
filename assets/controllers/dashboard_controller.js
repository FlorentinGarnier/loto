import { Controller } from '@hotwired/stimulus';
import { adminCsrfToken } from '../csrf.js';

export default class extends Controller {
    static targets = ['potentials', 'potentialsContainer', 'mercureStatus', 'lastNumber', 'grid', 'draws', 'progression'];

    static values = {
        eventId: Number,
        gameId: Number,
        mercureUrl: String,
        toggleUrl: String,
        potentialsFragmentUrl: String
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

        this.gridTarget.addEventListener('click', async (e) => {
            const btn = e.target.closest('button.cell');
            if (!btn) return;

            const num = btn.dataset.number;
            const url = this.toggleUrlValue.replace('/0', '/' + num);

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    body: new URLSearchParams({ _token: adminCsrfToken() })
                });
                if (res.ok) {
                    btn.classList.toggle('is-drawn');
                }
            } catch (err) {
                console.error('Error toggling number:', err);
            }
        });
    }

    setupMercure() {
        if (!this.hasMercureStatusTarget || !this.eventIdValue) return;

        this.setMercureStatus(false);

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        try {
            const topic = encodeURIComponent(`/events/${this.eventIdValue}/games/${this.gameIdValue}/state`);
            const url = `${this.mercureUrlValue}?topic=${topic}`;

            this.eventSource = new EventSource(url);

            this.eventSource.onopen = () => {
                this.setMercureStatus(true);
            };

            this.eventSource.onmessage = async (e) => {
                await this.handleMercureMessage(JSON.parse(e.data));
            };

            this.eventSource.onerror = () => {
                this.setMercureStatus(false);
            };

            // Refresh status on page visibility change
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && this.eventSource) {
                    this.setMercureStatus(this.eventSource.readyState === 1);
                }
            });
        } catch (e) {
            console.error('Mercure connection error:', e);
            this.setMercureStatus(false);
        }
    }

    async handleMercureMessage(data) {
        // Update potentials count
        if (data.potentialsCount !== undefined && this.hasPotentialsTarget) {
            const currentPotentials = parseInt(this.potentialsTarget.textContent);

            // Show notification if new potential winner
            if (data.potentialsCount > currentPotentials) {
                this.notifyNewPotentialWinner();
            }

            this.potentialsTarget.textContent = data.potentialsCount;
        }

        // Refresh potentials list
        if (this.hasPotentialsContainerTarget && this.potentialsFragmentUrlValue) {
            try {
                const res = await fetch(this.potentialsFragmentUrlValue);
                if (res.ok) {
                    this.potentialsContainerTarget.innerHTML = await res.text();
                }
            } catch (err) {
                console.error('Error refreshing potentials:', err);
            }
        }

        // Update draws count and progression
        if (data.draws) {
            const drawsCount = data.draws.length;

            // Update draws KPI
            if (this.hasDrawsTarget) {
                this.drawsTarget.textContent = drawsCount;
            }

            // Update progression KPI
            if (this.hasProgressionTarget) {
                this.progressionTarget.textContent = `${drawsCount}/90`;
            }

            // Update last number
            if (this.hasLastNumberTarget) {
                const lastNum = drawsCount > 0 ? data.draws[data.draws.length - 1] : '—';
                this.lastNumberTarget.textContent = lastNum;

                this.lastNumberTarget.classList.toggle('is-set', lastNum !== '—');
            }
        }
    }

    setMercureStatus(ok) {
        if (!this.hasMercureStatusTarget) return;

        this.mercureStatusTarget.textContent = ok ? 'OK' : 'Hors ligne';
        this.mercureStatusTarget.classList.toggle('pill--ok', ok);
        this.mercureStatusTarget.classList.toggle('pill--off', !ok);
    }

    notifyNewPotentialWinner() {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Nouveau gagnant potentiel !');
        } else {
            alert('Nouveau gagnant potentiel !');
        }
    }
}
