import {Controller} from '@hotwired/stimulus';

/**
 * light / dark / auto switch.
 *
 * The stored choice is applied before first paint by the inline script in
 * base.html.twig - this controller only owns the interactive half: reacting to
 * clicks, marking the active option and, while on "auto", following the OS as it
 * changes. The active option cannot be server-rendered because the choice lives
 * in localStorage, not in the session.
 */

const STORAGE_KEY = 'bc-theme';
const DEFAULT_CHOICE = 'auto';

export default class extends Controller {

	static targets = ['option'];

	connect() {
		this.media = window.matchMedia('(prefers-color-scheme: dark)');
		this.onSystemChange = () => {
			if (this.#choice() === 'auto') {
				this.#apply('auto');
			}
		};

		this.media.addEventListener('change', this.onSystemChange);
		this.#mark(this.#choice());
	}

	disconnect() {
		this.media.removeEventListener('change', this.onSystemChange);
	}

	select(event) {
		event.preventDefault();

		const choice = event.params.choice;
		this.#store(choice);
		this.#apply(choice);
		this.#mark(choice);
	}

	#apply(choice) {
		const dark = choice === 'auto' ? this.media.matches : choice === 'dark';
		document.documentElement.dataset.theme = dark ? 'dark' : 'light';
	}

	#mark(choice) {
		this.optionTargets.forEach((option) => {
			const active = option.dataset.appearanceChoiceParam === choice;

			option.classList.toggle('btn-accent', active);
			option.classList.toggle('btn-ghost', !active);
			option.setAttribute('aria-pressed', String(active));
		});
	}

	#choice() {
		try {
			return localStorage.getItem(STORAGE_KEY) || DEFAULT_CHOICE;
		} catch (e) {
			// storage blocked (private mode, strict cookie policy) - follow the OS instead
			return DEFAULT_CHOICE;
		}
	}

	#store(choice) {
		try {
			localStorage.setItem(STORAGE_KEY, choice);
		} catch (e) {
			// storage blocked - the choice still applies, it just will not survive a reload
		}
	}
}
