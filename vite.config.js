import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/enhancements.css', 'resources/css/premium-overrides.css', 'resources/css/signalement.css', 'resources/css/back-zones-alerts.css', 'resources/css/front-zones-alerts.css', 'resources/js/app.js', 'resources/js/zone-map.js', 'resources/js/zones-alerts.js', 'resources/js/front-zones-alerts.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
