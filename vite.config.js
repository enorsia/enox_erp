import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // ── Core ──
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pages/users.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '$': 'jquery',
            'jQuery': 'jquery',
            'jquery-validation': 'jquery-validation',
            'sweetalert2': 'sweetalert2',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});