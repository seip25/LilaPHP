import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

/**
 * Vite Configuration for LilaPHP Framework Integration.
 * Outputs assets to `frontend/js/.vite/` and generates a manifest file for PHP to read in production.
 * 
 * @type {import('vite').UserConfig}
 */
export default defineConfig({
  plugins: [react()],
  root: './',
  publicDir: 'public',
  build: {
    outDir: 'frontend/js/.vite',
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: {
      input: 'frontend/src/main.jsx',
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    cors: true,
    hmr: {
      host: 'localhost',
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './frontend/src'),
    },
  },
});
