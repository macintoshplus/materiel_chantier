import { Controller } from '@hotwired/stimulus';

/**
 * Affiche la section correspondant au mode choisi (boutons radio) : rectangle rapide ou tableau de sommets.
 */
export default class extends Controller {
    static targets = ['section'];

    connect() {
        this.appliquer();
    }

    appliquer() {
        const choisi = this.element.querySelector('input[type="radio"]:checked');
        const mode = choisi ? choisi.value : 'rectangle';
        this.sectionTargets.forEach((section) => {
            section.hidden = section.getAttribute('data-mode') !== mode;
        });
    }
}
