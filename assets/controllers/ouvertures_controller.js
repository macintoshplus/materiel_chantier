import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];
    static values = {
        index: Number
    }

    connect() {
        if (isNaN(this.indexValue)) {
            this.indexValue = this.containerTarget.children.length;
        }
    }

    add(event) {
        event.preventDefault();

        const prototype = this.containerTarget.dataset.prototype;
        const newForm = prototype.replace(/__name__/g, this.indexValue);

        this.indexValue++;

        const wrapper = document.createElement('div');
        wrapper.classList.add('ouverture-item');
        wrapper.innerHTML = newForm;

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.classList.add('btn', 'btn-danger', 'btn-sm');
        removeButton.textContent = 'Supprimer';
        removeButton.addEventListener('click', () => {
            wrapper.remove();
        });

        wrapper.appendChild(removeButton);
        this.containerTarget.appendChild(wrapper);
    }
}
