import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js','resources/js/app2.js', 'resources/js/messages.js'],
            refresh: true,
        }),
    ],
    alias: {
        'vue': 'vue/dist/vue.esm-bundler.js'
    }
});
