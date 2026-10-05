import {Controller} from '@hotwired/stimulus';
import {Datepicker} from '@tito10047/vanilla-js-datepicker';
import {Timepicker} from '@tito10047/vanilla-js-timepicker';

/**
 * The day and time half of a performance panel's window control.
 *
 * The rest of the dashboard needs no JavaScript of its own - a `<select data-model>` is the whole
 * of an interaction, and the server re-reads and re-renders. A day and a time are the exception:
 * the browser's own `datetime-local` renders differently in every browser, cannot be themed at all,
 * and on Firefox has no calendar. So two widgets, and this is the glue.
 *
 * Three things it exists to do, each of them a thing that silently does not work otherwise:
 *
 *  - **Join two inputs into one prop.** The date and the time are half a value each and
 *    `data-model` binds one. The hidden field is the value; these two are its editor.
 *  - **Fire the event Live Component listens for.** The pickers dispatch only their own
 *    `vdp:change` / `vtp:change` CustomEvents - they never touch the input's native `input` or
 *    `change`. Without the dispatch below the calendar visibly closes on a new date and the page
 *    never re-reads.
 *  - **Destroy the pickers on disconnect.** A Live panel re-renders on a timer (`data-poll`), and
 *    an instance per render, each with its own listeners on `document`, is a leak per poll.
 *
 * The dropdowns live on `document.body` - the libraries' own default - so they are outside anything
 * Live's DOM morphing touches. The two inputs are not, which is why they carry `data-live-ignore`
 * in the template: between picking a day and picking a time the client holds half a value the
 * server has never been told, and these panels poll.
 */

/** What the hidden field, the `at` query parameter and PerfRange::AT_FORMAT all agree on. */
const DATE_FORMAT = 'YYYY-MM-DD';
const TIME_FORMAT = 'HH:mm';

/**
 * "Do not theme yourself", spelled the only way the libraries accept.
 *
 * Both default to `theme: 'auto'`, which puts `data-vdp-theme="auto"` on the dropdown, and their
 * stylesheets then redeclare every colour on that element under
 * `@media (prefers-color-scheme: dark)`. A custom property declared on an element always beats the
 * same property inherited from `:root`, so the `--vdp-` and `--vtp-` mapping in app.css - which is
 * what puts these widgets on `data-theme` and the `--bc-` tokens - would simply never reach them on
 * a machine set to dark. `'light'` is the one value neither stylesheet has a rule for, so it leaves
 * the mapping to inherit through untouched. It does not mean the picker is light.
 */
const INERT_THEME = 'light';

export default class extends Controller {

	static targets = ['value', 'date', 'time'];

	connect() {
		// from <html lang>, which base.html.twig renders from the request locale. Not a Stimulus
		// value: a Twig component has no request when it is rendered from a test or a command, and
		// the page's own language is the right answer anyway. An unknown code is not an error -
		// both libraries fall back to a built-in locale.
		const locale = document.documentElement.lang || 'en';

		this.datepicker = new Datepicker(this.dateTarget, {
			format: DATE_FORMAT,
			locale,
			theme: INERT_THEME,
			showTodayButton: true,
			showToggleIcon: true,
			onChange: () => this.#publish(),
		});

		// `showNowButton: false` on purpose, twice over. It inserts the *browser's* clock, and
		// `at` is read on the server's - which is where the buckets are, so on a machine an hour
		// off the server it would quietly select an hour with no traffic in it. And it would be a
		// second button saying "Now" next to this control's own, which means something else
		// entirely: go back to following the clock.
		this.timepicker = new Timepicker(this.timeTarget, {
			format: TIME_FORMAT,
			locale,
			theme: INERT_THEME,
			minuteStep: 5,
			showNowButton: false,
			showToggleIcon: true,
			onChange: () => this.#publish(),
		});
	}

	disconnect() {
		this.datepicker?.destroy();
		this.timepicker?.destroy();
		this.datepicker = null;
		this.timepicker = null;
	}

	/** Back to the window that follows the clock: an empty `at` is what the server reads as live. */
	now(event) {
		event.preventDefault();

		this.datepicker?.clear(false);
		this.timepicker?.clear();
		this.#write('');
	}

	/**
	 * A day on its own is not a window start, so nothing is published until both halves are set.
	 *
	 * Midnight is not a safe default for the missing half: "5 October" would silently become
	 * "5 October 00:00", and an hour-long window there says the application was idle when it was
	 * only asleep.
	 */
	#publish() {
		const date = this.datepicker?.getValue();
		const time = this.timepicker?.getValue();

		if (!date || !time) {
			return;
		}

		this.#write(`${date}T${time}`);
	}

	#write(value) {
		if (this.valueTarget.value === value) {
			return;
		}

		this.valueTarget.value = value;

		// the one line this controller is really here for - see the note at the top
		this.valueTarget.dispatchEvent(new Event('input', {bubbles: true}));
	}
}
