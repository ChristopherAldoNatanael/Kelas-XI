import './bootstrap';
import Chart from 'chart.js/auto';

// Dashboard charts are rendered from inline Blade scripts. Exposing Chart here
// keeps the library bundled (no CDN) while preserving the existing page code.
window.Chart = Chart;
