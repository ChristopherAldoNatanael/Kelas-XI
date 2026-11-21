import './bootstrap';
import Chart from 'chart.js/auto';
import { initPickupScanner } from './pickup-scanner';
import { initAdminUI } from './admin/ui';
import { initAdminLayout } from './admin/layout';
import { initCommandPalette } from './admin/command-palette';

// Dashboard charts are rendered from inline Blade scripts. Exposing Chart here
// keeps the library bundled (no CDN) while preserving the existing page code.
window.Chart = Chart;

// Halaman pengambilan Pet Shop memakai pemindai QR berbasis kamera. Modulnya
// hanya melakukan apa-apa kalau elemen pemindai benar-benar ada di halaman.
initPickupScanner();

// Perilaku bersama konsol admin: Escape/fokus pada modal dan satu jalur
// konfirmasi untuk tindakan destruktif (docs/AUDIT_FIXES_REPORT.md UIX-08).
initAdminUI();

// Kerangka konsol admin (laci sidebar, tema, toast) dan command palette kini
// berupa modul, bukan <script> inline per halaman (UIX-19).
initAdminLayout();
initCommandPalette();
