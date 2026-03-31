import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    base: './',
    plugins: [react()],
    build: {
        outDir: 'build',
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: {
                'admin/index': resolve(__dirname, 'src/admin/index.tsx'),
                'portal/index': resolve(__dirname, 'src/portal/index.tsx'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src/admin'),
            '@portal': resolve(__dirname, 'src/portal'),
        },
    },
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    },
});

