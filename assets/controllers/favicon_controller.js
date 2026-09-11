import {Controller} from '@hotwired/stimulus';

/**
 * Swaps the page favicon to the severity-coloured variant supplied by the Favicon
 * component, so the tab itself reports the worst current state.
 */
export default class extends Controller {
	static values = {
		icon: String
	}

	iconValueChanged() {
		let link = document.querySelector("link[rel~='icon'][type='image/svg+xml']");
		if (!link) {
			link = document.createElement('link');
			link.rel = 'icon';
			document.head.appendChild(link);
		}
		link.href = this.iconValue
	}
}
