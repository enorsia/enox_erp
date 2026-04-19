import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Main entry (imports all CSS + common JS)
                'resources/js/app.js',

                // Page-specific JS
                'resources/js/pages/roles/index.js',
                'resources/js/pages/roles/create.js',
                'resources/js/pages/roles/edit.js',
                'resources/js/pages/users/script.js',
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
