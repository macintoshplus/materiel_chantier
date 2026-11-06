import { Controller } from '@hotwired/stimulus';

/**
 * Survoler (ou focaliser) un élément du plan ou de la liste met en évidence l'autre :
 * les deux portent la même clé `data-highlight-key` (wall-ID, partition-ID, opening-ID).
 */
export default class extends Controller {
    on(event) {
        this.basculer(event, true);
    }

    off(event) {
        this.basculer(event, false);
    }

    basculer(event, actif) {
        if (!(event.target instanceof Element)) {
            return;
        }
        const source = event.target.closest('[data-highlight-key]');
        if (!source) {
            return;
        }
        const cle = source.getAttribute('data-highlight-key');
        this.element.querySelectorAll('[data-highlight-key]').forEach((element) => {
            if (element.getAttribute('data-highlight-key') === cle) {
                element.classList.toggle('is-highlighted', actif);
            }
        });
    }
}
