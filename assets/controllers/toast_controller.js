import {Controller} from '@hotwired/stimulus';
import {Toast} from "bootstrap";
import htmlCreator from "../components/createElementFromHTML";

export default class extends Controller {

	static targets = ['container'];

	show({detail: {title, text, confirm}}) {
		let html = `
			<div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
				<div class="toast-header">
					<strong class="me-auto">${title}</strong>
					<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
				</div>
				<div class="toast-body">
					${text}
				</div>
			</div>
`
		let div = htmlCreator(html);
		this.containerTarget.appendChild(div);
		let toast = new Toast(div);
		toast.show();
		div.addEventListener('hidden.bs.toast', function () {
			confirm();
			div.remove();
		})
	}
}
