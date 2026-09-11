import {Controller} from '@hotwired/stimulus';

/**
 * Toast stack, replacing Bootstrap's Toast component.
 *
 * Contract with MyController#showToast, which is what every caller actually uses:
 * a `toast:show` event carrying {title, text, confirm}. `confirm` is the resolve
 * function of the caller's promise and MUST be invoked exactly once, when the toast
 * goes away - otherwise the caller awaits forever.
 *
 * `text` is either a string or a DOM node the caller keeps a reference to (see
 * warning-sound_controller, which wires a click handler on the node it passes in).
 *
 * A caller holding such a node can dismiss the toast it lives in by dispatching
 * `toast:dismiss` on the closest [data-toast] element. Removing the node from the
 * DOM by hand would skip the teardown and leak the promise.
 */

const DISMISS_EVENT = 'toast:dismiss';
const AUTO_DISMISS_MS = 8000;
const LEAVE_MS = 200;

const CLOSE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"'
	+ ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
	+ '<path d="M18 6 6 18M6 6l12 12"/></svg>';

export default class extends Controller {

	static targets = ['container'];

	static values = {
		autoDismiss: {type: Number, default: AUTO_DISMISS_MS},
	};

	show({detail: {title, text, confirm}}) {
		const toast = this.#build(title, text);
		const dismiss = this.#dismisser(toast, confirm);

		toast.querySelector('[data-toast-close]').addEventListener('click', dismiss);
		toast.addEventListener(DISMISS_EVENT, dismiss);

		this.containerTarget.appendChild(toast);

		// one frame on screen in the initial state, so the transition has something to run from
		requestAnimationFrame(() => toast.classList.remove('opacity-0', 'translate-x-3'));

		if (this.autoDismissValue > 0) {
			toast.dismissTimer = window.setTimeout(dismiss, this.autoDismissValue);
		}
	}

	/**
	 * Returns an idempotent teardown: animate out, settle the caller's promise, drop the node.
	 */
	#dismisser(toast, confirm) {
		let dismissed = false;

		return () => {
			if (dismissed) {
				return;
			}
			dismissed = true;
			window.clearTimeout(toast.dismissTimer);
			toast.classList.add('opacity-0', 'translate-x-3');

			// a fixed timeout rather than transitionend: under prefers-reduced-motion the
			// transition is effectively instant and the event is easy to miss
			window.setTimeout(() => {
				toast.remove();
				confirm(toast);
			}, LEAVE_MS);
		};
	}

	#build(title, text) {
		const toast = document.createElement('div');
		toast.dataset.toast = '';
		toast.setAttribute('role', 'alert');
		toast.setAttribute('aria-live', 'assertive');
		toast.setAttribute('aria-atomic', 'true');
		toast.className = 'panel panel-glow pointer-events-auto w-80 max-w-[calc(100vw-2rem)]'
			+ ' translate-x-3 opacity-0 transition duration-200 ease-out';

		const header = document.createElement('div');
		header.className = 'flex items-center gap-2 border-b border-line px-3 py-2';

		const heading = document.createElement('strong');
		heading.className = 'flex-1 truncate text-sm font-semibold tracking-wide';
		heading.textContent = title;

		const close = document.createElement('button');
		close.type = 'button';
		close.dataset.toastClose = '';
		close.className = 'btn btn-ghost btn-icon btn-sm';
		close.setAttribute('aria-label', 'Close');
		close.innerHTML = CLOSE_ICON;

		const body = document.createElement('div');
		body.className = 'px-3 py-2 text-sm text-muted';
		if (typeof text === 'string') {
			body.textContent = text;
		} else {
			body.append(text);
		}

		header.append(heading, close);
		toast.append(header, body);

		return toast;
	}
}
