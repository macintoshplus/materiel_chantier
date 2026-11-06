import { Controller } from '@hotwired/stimulus';

/**
 * Détecte les modifications non enregistrées d'un formulaire. Quitter la page (lien, Turbo Drive) ouvre un
 * dialogue : « Save and continue » enregistre puis poursuit, « Discard » abandonne, « Cancel » reste sur la page.
 * L'enregistrement n'est jamais automatique.
 */
export default class extends Controller {
    static targets = ['dialogue', 'suite', 'etat'];
    static values = { dirty: Boolean };

    connect() {
        this.avantVisite = this.avantVisite.bind(this);
        this.avantDecharge = this.avantDecharge.bind(this);
        document.addEventListener('turbo:before-visit', this.avantVisite);
        window.addEventListener('beforeunload', this.avantDecharge);
        this.afficherEtat();
    }

    disconnect() {
        document.removeEventListener('turbo:before-visit', this.avantVisite);
        window.removeEventListener('beforeunload', this.avantDecharge);
    }

    marquer() {
        this.dirtyValue = true;
    }

    soumettre() {
        // L'envoi du formulaire n'est pas un abandon : pas de dialogue pendant l'enregistrement.
        this.dirtyValue = false;
    }

    dirtyValueChanged() {
        this.afficherEtat();
    }

    afficherEtat() {
        if (this.hasEtatTarget) {
            this.etatTarget.hidden = !this.dirtyValue;
        }
    }

    avantVisite(event) {
        if (!this.dirtyValue) {
            return;
        }
        event.preventDefault();
        this.destination = event.detail.url;
        if (typeof this.dialogueTarget.showModal === 'function') {
            this.dialogueTarget.showModal();
        } else {
            this.dialogueTarget.setAttribute('open', '');
        }
    }

    avantDecharge(event) {
        if (this.dirtyValue) {
            event.preventDefault();
            event.returnValue = '';
        }
    }

    enregistrerEtContinuer() {
        this.fermer();
        try {
            const url = new URL(this.destination, window.location.href);
            this.suiteTarget.value = url.pathname + url.search + url.hash;
        } catch (e) {
            this.suiteTarget.value = '';
        }
        this.element.requestSubmit();
    }

    abandonner() {
        this.fermer();
        this.dirtyValue = false;
        if (window.Turbo && this.destination) {
            window.Turbo.visit(this.destination);
        } else if (this.destination) {
            window.location.assign(this.destination);
        }
    }

    annuler() {
        this.fermer();
    }

    fermer() {
        if (typeof this.dialogueTarget.close === 'function') {
            this.dialogueTarget.close();
        } else {
            this.dialogueTarget.removeAttribute('open');
        }
    }
}
