import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    // cmdk pulls its own React through Radix; without deduping, the bundle ends up
    // with two React instances and hooks throw "Invalid hook call".
    resolve: {
        dedupe: ['react', 'react-dom'],
    },
    plugins: [
        laravel({
            // Geist ships through @fontsource-variable/geist and is imported from
            // app.css, so no external font host is contacted at runtime.
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
