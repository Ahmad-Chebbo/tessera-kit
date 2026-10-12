import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { tesseraModules } from './vite.modules.js';

export default defineConfig({
    // The kit keeps state in modules (locale, toasts, confirm dialogs). Pre-bundled, `app.js` and the `.vue` files, which
    // Vite cannot pre-bundle, would each get their own copy of that state.
    optimizeDeps: {
        exclude: ['@tessera/tessera-ui'],
    },
    plugins: [
        tesseraModules(),
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
