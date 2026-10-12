import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { moduleAliases } from './vite.modules.js';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: [{ find: '@', replacement: fileURLToPath(new URL('./resources/js', import.meta.url)) }, ...moduleAliases()],
    },
    test: {
        environment: 'jsdom',
        include: [
            'resources/js/**/*.test.js',
            'packages/ui/src/**/*.test.js',
            '{app/Modules,packages/modules,packages/base/modules,packages/saas/modules}/*/{Tests/js,Resources/js}/**/*.test.js',
        ],
        globals: false,
    },
});
