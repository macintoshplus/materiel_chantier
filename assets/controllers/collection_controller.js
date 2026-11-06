import { Controller } from '@hotwired/stimulus';

/**
 * Liste de lignes de formulaire (prototype Symfony) : ajouter ou retirer une ligne ne change que la page ;
 * rien n'est enregistré avant « Save ».
 */
export default class extends Controller {
    static targets = ['liste'];
    static values = { prototype: String, index: Number };

    ajouter(event) {
        event.preventDefault();
        const html = this.prototypeValue.replace(/__name__/g, String(this.indexValue));
        this.indexValue += 1;
        this.listeTarget.insertAdjacentHTML('beforeend', html);
        this.signaler();
    }

    retirer(event) {
        event.preventDefault();
        const ligne = event.currentTarget.closest('[data-collection-item]');
        if (ligne) {
            ligne.remove();
            this.signaler();
        }
    }

    signaler() {
        this.element.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
