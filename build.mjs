import * as esbuild from 'esbuild';
import { readFileSync, writeFileSync, mkdirSync, existsSync, cpSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const outDir = join(__dirname, 'build', 'admin');

if (!existsSync(outDir)) {
    mkdirSync(outDir, { recursive: true });
}

await esbuild.build({
    entryPoints: [join(__dirname, 'src', 'admin', 'index.tsx')],
    bundle: true,
    minify: true,
    outfile: join(outDir, 'index.js'),
    format: 'iife',
    define: {
        'process.env.NODE_ENV': '"production"',
    },
    jsx: 'automatic',
    loader: {
        '.tsx': 'tsx',
        '.ts': 'ts',
        '.css': 'css',
    },
    external: [],
    logLevel: 'info',
});

writeFileSync(join(outDir, 'asset-manifest.json'), JSON.stringify({ version: '1.0.0', builtAt: new Date().toISOString() }, null, 2));

console.log('Build complete!');
