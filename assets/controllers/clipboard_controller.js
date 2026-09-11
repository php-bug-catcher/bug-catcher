import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

	static targets = ['icon', 'success'];

	static values = {
		text: String,
		successDuration: {type: Number, default: 1500},
	};

	disconnect() {
		this.#clearTimer();
	}

	async copy(event) {
		event.preventDefault();
		// the buttons live inside a disclosure trigger, a bubbling click would toggle it
		event.stopPropagation();

		try {
			// navigator.clipboard is unavailable on plain http, where the dashboard often runs
			if (navigator.clipboard && window.isSecureContext) {
				await navigator.clipboard.writeText(this.textValue);
			} else {
				this.#copyFallback(this.textValue);
			}
		} catch (e) {
			this.#copyFallback(this.textValue);
		}

		this.#showSuccess();
	}

	#copyFallback(text) {
		const textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild(textarea);
		textarea.select();
		document.execCommand('copy');
		textarea.remove();
	}

	#showSuccess() {
		if (!this.hasIconTarget || !this.hasSuccessTarget) {
			return;
		}
		this.#clearTimer();
		this.iconTarget.classList.add('hidden');
		this.successTarget.classList.remove('hidden');
		this.timer = window.setTimeout(() => {
			this.iconTarget.classList.remove('hidden');
			this.successTarget.classList.add('hidden');
			this.timer = null;
		}, this.successDurationValue);
	}

	#clearTimer() {
		if (this.timer) {
			window.clearTimeout(this.timer);
			this.timer = null;
		}
	}
}
