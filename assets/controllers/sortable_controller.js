import Sortable from 'sortablejs';
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String
    }

    connect() {
        this.sortable = new Sortable(this.element, {
            animation: 150,
            ghostClass: 'bg-blue-100',
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
                body: JSON.stringify({ order: order })
            });

            if (!response.ok) {
                console.error('Failed to save order');
            } else {
                this.updatePositionNumbers();
            }
        } catch (error) {
            console.error('Error saving order:', error);
        }
    }

    updatePositionNumbers() {
        const rows = this.element.querySelectorAll('tr[data-id]');
        rows.forEach((row, index) => {
            const positionCell = row.querySelector('td:first-child');
            if (positionCell) {
                positionCell.textContent = `#${index + 1}`;
            }
        });
    }
}
