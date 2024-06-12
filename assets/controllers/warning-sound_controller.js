import {Toast} from 'bootstrap';
import MyController from "./MyController";

export default class extends MyController {
	static values = {
		soundUrl: String,
		id: String
	};

	connect() {
		this.audio = new Audio(this.soundUrlValue);

	}

	idValueChanged() {
		if (!this.soundUrlValue || !this.audio) {
			return;
		}
		let promise = this.audio.play();
		if (promise !== undefined) {
			promise.then(_ => {
				// Autoplay started!
				console.log('Autoplay started!');
			}).catch(error => {
				// Autoplay was prevented.
				console.log('Autoplay was prevented.');
				this.showToast('Warning', 'Please click here to enable sound')
					.then(() => {
						console.log('Toast closed');
					});
				// Show a "Play" button so that user can start playback.
			});
		}
	}
}
