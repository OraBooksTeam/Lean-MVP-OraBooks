import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
  // Relative base so CSS/JS preloads resolve under wp-content/plugins/.../assets/react/
  base: './',
  plugins: [react(), tailwindcss()],
  root: 'src',
  publicDir: '../public',
  build: {
    outDir: path.resolve(__dirname, '../OraBooks Lean MVP/assets/react'),
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: {
        admin: path.resolve(__dirname, 'src/pages/admin/main.tsx'),
        frontend: path.resolve(__dirname, 'src/pages/frontend/main.tsx'),
      },
      output: {
        entryFileNames: (chunkInfo) => {
          const name = chunkInfo.name;
          if (name === 'admin') return 'admin.js';
          if (name === 'frontend') return 'frontend.js';
          return '[name].js';
        },
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
        // Keep admin entry self-contained — no runtime chunk fetches in wp-admin.
        manualChunks(id) {
          if (id.includes('node_modules')) {
            return 'vendor';
          }
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
});
