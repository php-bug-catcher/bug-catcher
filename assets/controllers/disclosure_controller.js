import {Controller} from '@hotwired/stimulus';

/**
 * Replaces Bootstrap's accordion/collapse pair.
 *
 * A trigger is linked to its panel through aria-controls, the same attribute the
 * markup needs for accessibility anyway - no parallel index bookkeeping, and no
 * global ids like Bootstrap's data-bs-parent (the detail page rendered two
 * accordions sharing one id before this).
 *
 * singleOpen replicates data-bs-parent: opening one panel closes its siblings.
 *
 * Open state is driven entirely by aria-expanded in the server-rendered markup,
 * so a component that pre-opens a frame (Detail/StackTrace) needs no extra flag.
 */
export default class extends Controller {

	static targets = ['trigger', 'panel'];

	static values = {
		singleOpen: {type: Boolean, default: false},
	};

	connect() {
		this.triggerTargets.forEach((trigger) => {
			this.#apply(trigger, this.#isOpen(trigger));
		});
	}

	toggle(event) {
		const trigger = event.currentTarget;
		const open = !this.#isOpen(trigger);

		if (open && this.singleOpenValue) {
			this.triggerTargets
				.filter((other) => other !== trigger)
				.forEach((other) => this.#apply(other, false));
		}

		this.#apply(trigger, open);
	}

	#isOpen(trigger) {
		return trigger.getAttribute('aria-expanded') === 'true';
	}

	#apply(trigger, open) {
		trigger.setAttribute('aria-expanded', String(open));

		const panel = this.#panelFor(trigger);
		if (panel) {
			panel.classList.toggle('hidden', !open);
		}
	}

	#panelFor(trigger) {
		const id = trigger.getAttribute('aria-controls');

		return id ? this.element.querySelector(`#${CSS.escape(id)}`) : null;
	}
}
