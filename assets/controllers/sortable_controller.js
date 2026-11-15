import Sortable from 'sortablejs';
import { Controller } from '@hotwired/stimulus';
import { adminCsrfToken } from '../csrf.js';

export default class extends Controller {
    static values = {
        url: String
    }

    connect() {
        this.sortable = new Sortable(this.element, {
            animation: 150,
            ghostClass: 'is-dragging',
            onEnd: this.onEnd.bind(this)
        });
    }

    async onEnd() {
        const order = this.sortable.toArray();
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ order: order, _token: adminCsrfToken() })
            });

            if (!response.ok) {
                this.restore();
            } else {
                this.updatePositionNumbers();
            }
        } catch (error) {
            this.restore();
        }
    }

    // L'ordre n'a pas été enregistré : on réaffiche celui du serveur plutôt qu'un ordre faux
    restore() {
        window.location.reload();
    }

    updatePositionNumbers() {
        this.element.querySelectorAll('[data-id]').forEach((item, index) => {
            const position = item.querySelector('[data-position]');
            if (position) position.textContent = `#${index + 1}`;
        });
    }
}
