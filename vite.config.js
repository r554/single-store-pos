import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'prompt',
            includeAssets: ['favicon.ico', 'robots.txt'],
            manifest: {
                name: 'Single Store POS',
                short_name: 'POS',
                description: 'Aplikasi kasir single-store dengan mode offline',
                theme_color: '#0f172a',
                background_color: '#ffffff',
                display: 'standalone',
                start_url: '/',
                icons: [
                    {
                        src: 'pwa-icons/icon-64x64.png',
                        sizes: '64x64',
                        type: 'image/png',
                    },
                    {
                        src: 'pwa-icons/icon-144x144.png',
                        sizes: '144x144',
                        type: 'image/png',
                    },
                    {
                        src: 'pwa-icons/icon-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: 'pwa-icons/icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                    {
                        src: 'pwa-icons/icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                navigateFallbackDenylist: [/^\/api\//],
                runtimeCaching: [
                    {
                        // Katalog POS: stale-while-revalidate, dibutuhkan untuk mode offline.
                        urlPattern: /\/api\/pos\/catalog/,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'pos-catalog',
                            cacheableResponse: { statuses: [200] },
                            expiration: { maxEntries: 10, maxAgeSeconds: 86400 },
                        },
                    },
                ],
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
