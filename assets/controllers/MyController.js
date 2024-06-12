import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
	showToast(title, text) {
		return new Promise((resolve, reject) => {
			this.dispatch(
				"show",
				{
					prefix: "toast",
					detail: {
						title: title,
						text: text,
						confirm: resolve,
					}
				}
			)
		});
	}
}