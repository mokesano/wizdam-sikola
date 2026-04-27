import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],

  // Build output ke public/app/ agar PHP bisa serve static assets
  build: {
    outDir: 'public/app',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        // Hash-based filenames untuk cache busting
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },

  // Dev server proxy ke PHP backend
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/auth': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },

  // Env prefix REACT_APP_ tetap didukung untuk kompatibilitas
  envPrefix: ['VITE_', 'REACT_APP_'],
});
