import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath, URL } from 'node:url';

// Standalone build for this theme's bundle, separate from the root vite.config.js (panel/Inertia).
// Outputs one committed, self-contained IIFE script; no merchant ever runs this build.
export default defineConfig({
    plugins: [react()],
    // Without this, Vite copies the whole Laravel public/ folder into this theme's assets/js output.
    publicDir: false,
    build: {
        outDir: fileURLToPath(new URL('./assets/js', import.meta.url)),
        emptyOutDir: false,
        rollupOptions: {
            input: fileURLToPath(new URL('./resources/js/app.jsx', import.meta.url)),
            output: {
                entryFileNames: 'app.js',
                format: 'iife',
            },
        },
    },
});
