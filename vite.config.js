import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/filament/rich-content-plugins/figure.js',
                'resources/js/filament/rich-content-plugins/figcaption.js',
                'resources/js/filament/rich-content-plugins/div.js',
                'resources/js/filament/rich-content-plugins/iframe.js',
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
