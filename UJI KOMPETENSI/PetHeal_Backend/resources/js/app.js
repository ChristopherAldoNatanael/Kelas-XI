import './bootstrap';
import Chart from 'chart.js/auto';
import { initPickupScanner } from './pickup-scanner';

// Dashboard charts are rendered from inline Blade scripts. Exposing Chart here
// keeps the library bundled (no CDN) while preserving the existing page code.
window.Chart = Chart;

// Halaman pengambilan Pet Shop memakai pemindai QR berbasis kamera. Modulnya
// hanya melakukan apa-apa kalau elemen pemindai benar-benar ada di halaman.
initPickupScanner();
