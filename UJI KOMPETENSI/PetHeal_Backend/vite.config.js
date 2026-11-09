import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // pin-location: pemilih titik lokasi klinik, dimuat hanya di form
            // klinik (create/edit/profil) lewat @vite/@push('scripts').
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pin-location.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
