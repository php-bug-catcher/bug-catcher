import {Controller} from '@hotwired/stimulus';

/**
 * Replaces Bootstrap's dropdown (data-bs-toggle="dropdown").
 *
 * Expects a trigger carrying aria-expanded and a menu target. Closes on outside
 * click, on Escape and after a menu item is activated, so navigating away never
 * leaves an orphaned open menu behind a Live Component re-render.
 */
export default class extends Controller {

	static targets = ['trigger', 'menu'];

	connect() {
		// bound once so disconnect() can actually remove them again
		this.onDocumentClick = (event) => {
			if (!this.element.contains(event.target)) {
				this.close();
			}
		};
		this.onKeydown = (event) => {
			if (event.key === 'Escape') {
				this.close();
			}
		};

		document.addEventListener('click', this.onDocumentClick);
		document.addEventListener('keydown', this.onKeydown);
		this.close();
	}

	disconnect() {
		document.removeEventListener('click', this.onDocumentClick);
		document.removeEventListener('keydown', this.onKeydown);
	}

	toggle(event) {
		event.preventDefault();
		this.#setExpanded(this.menuTarget.classList.contains('hidden'));
	}

	close() {
		this.#setExpanded(false);
	}

	#setExpanded(expanded) {
		this.menuTarget.classList.toggle('hidden', !expanded);

		if (this.hasTriggerTarget) {
			this.triggerTarget.setAttribute('aria-expanded', String(expanded));
		}
	}
}
