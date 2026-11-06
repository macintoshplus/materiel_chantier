import { Controller } from '@hotwired/stimulus';

/**
 * Création d'une cloison : un premier clic sur le plan fixe le début, le second la fin. Les coordonnées (mm)
 * remplissent les champs du formulaire ; l'axe y du plan SVG est inversé.
 */
export default class extends Controller {
    static targets = ['svg'];
    static values = { etape: Number };

    connect() {
        this.surClic = this.surClic.bind(this);
        this.element.addEventListener('click', this.surClic);
    }

    disconnect() {
        this.element.removeEventListener('click', this.surClic);
    }

    surClic(event) {
        const svg = this.hasSvgTarget ? this.svgTarget : null;
        if (!svg || typeof svg.createSVGPoint !== 'function') {
            return;
        }
        const matrice = svg.getScreenCTM();
        if (!matrice) {
            return;
        }
        const point = svg.createSVGPoint();
        point.x = event.clientX;
        point.y = event.clientY;
        const local = point.matrixTransform(matrice.inverse());
        const x = Math.round(local.x);
        const y = Math.round(-local.y);
        const prefixe = this.etapeValue % 2 === 0 ? 'debut' : 'fin';
        this.remplir(prefixe + 'X', x);
        this.remplir(prefixe + 'Y', y);
        this.etapeValue += 1;
    }

    remplir(nom, valeur) {
        const champ = document.querySelector('[name$="[' + nom + ']"]');
        if (champ) {
            champ.value = String(valeur);
            champ.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
}
