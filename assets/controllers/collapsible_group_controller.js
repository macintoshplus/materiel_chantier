import { Controller } from '@hotwired/stimulus';

/**
 * Groupe repliable (<details>) dont l'état ouvert/fermé est mémorisé dans le navigateur.
 * Le stockage peut être indisponible (navigation privée, données bloquées) : le groupe reste alors ouvert.
 */
export default class extends Controller {
    static values = { key: String };

    connect() {
        try {
            const etat = window.localStorage.getItem(this.cle());
            if (etat === 'closed') {
                this.element.open = false;
            } else if (etat === 'open') {
                this.element.open = true;
            }
        } catch (e) {
            // stockage indisponible : on garde l'état par défaut
        }
    }

    sauver() {
        try {
            window.localStorage.setItem(this.cle(), this.element.open ? 'open' : 'closed');
        } catch (e) {
            // stockage indisponible : l'état n'est simplement pas mémorisé
        }
    }

    cle() {
        return 'materiel_chantier.groupe.' + this.keyValue;
    }
}
