import { Controller } from '@hotwired/stimulus';

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
                const res = await fetch(url, { method: 'POST' });
                if (res.ok) {
                    btn.classList.toggle('bg-white');
                    btn.classList.toggle('bg-green-600');
                    btn.classList.toggle('text-white');
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

                if (lastNum !== '—') {
                    this.lastNumberTarget.classList.add('bg-amber-600', 'text-white');
                    this.lastNumberTarget.classList.remove('bg-gray-200');
                } else {
                    this.lastNumberTarget.classList.remove('bg-amber-600', 'text-white');
                    this.lastNumberTarget.classList.add('bg-gray-200');
                }
            }
        }
    }

    setMercureStatus(ok) {
        if (!this.hasMercureStatusTarget) return;

        this.mercureStatusTarget.textContent = ok ? 'OK' : 'Hors ligne';
        this.mercureStatusTarget.classList.remove(
            'bg-gray-300', 'bg-red-200', 'bg-green-200',
            'text-red-900', 'text-green-900'
        );

        if (ok) {
            this.mercureStatusTarget.classList.add('bg-green-200', 'text-green-900');
        } else {
            this.mercureStatusTarget.classList.add('bg-red-200', 'text-red-900');
        }
    }

    notifyNewPotentialWinner() {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Nouveau gagnant potentiel !');
        } else {
            alert('Nouveau gagnant potentiel !');
        }
    }
}
