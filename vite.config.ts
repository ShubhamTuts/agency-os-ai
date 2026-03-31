import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

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
            // Externalize React so the bundle uses WP's global React/ReactDOM.
            // WordPress and plugins like Elementor load wp-element (which wraps
            // the react/react-dom handles) on every admin page. Bundling our own
            // React creates a second instance and causes hook dispatcher conflicts.
            external: (id) => id === 'react' || id === 'react-dom' || id === 'react-dom/client' || id === 'react-dom/server',
            output: {
                globals: {
                    react: 'React',
                    'react-dom': 'ReactDOM',
                    'react-dom/client': 'ReactDOM',
                    'react-dom/server': 'ReactDOMServer',
                },
                entryFileNames: '[name].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src/admin'),
        },
    },
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    },
});

