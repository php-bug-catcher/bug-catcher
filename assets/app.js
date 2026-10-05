import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

/*
 * The two pickers' stylesheets, imported here rather than from perf-range_controller.js on
 * purpose: the controllers are lazily loaded through stimulus-bridge and `splitEntryChunks()` is
 * on, so CSS imported from one lands in a chunk `encore_entry_link_tags` never emits. Their
 * colours are remapped onto --bc-* tokens in styles/app.css, which is imported after them.
 */
import '@tito10047/vanilla-js-datepicker/dist/datepicker.css';
import '@tito10047/vanilla-js-timepicker/dist/timepicker.css';

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
