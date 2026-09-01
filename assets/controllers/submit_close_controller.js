import {Controller} from '@hotwired/stimulus';

/**
 * Submits a form in the background and closes the tab afterwards.
 *
 * The detail page is opened from the log list with target="_blank", and a browser only lets a
 * script close such a tab while it still holds a single history entry. Posting the form normally
 * would navigate and lose that, so the request goes out through fetch() instead. If the browser
 * refuses to close anyway - direct visit, tab restored from a session - the page is reloaded so the
 * new status is at least visible.
 */
export default class extends Controller {

	static values = {
		fallbackDelay: {type: Number, default: 200},
	};

	async submit(event) {
		event.preventDefault();

		const form = event.target;
		let response;
		try {
			response = await fetch(form.action, {
				method: 'POST',
				body: new FormData(form),
				credentials: 'same-origin',
				headers: {'X-Requested-With': 'XMLHttpRequest'},
			});
		} catch (e) {
			// offline, blocked, whatever - let the browser do the plain submit and show the result
			form.submit();
			return;
		}

		if (!response.ok) {
			form.submit();
			return;
		}

		window.close();
		window.setTimeout(() => {
			if (!window.closed) {
				window.location.reload();
			}
		}, this.fallbackDelayValue);
	}
}
