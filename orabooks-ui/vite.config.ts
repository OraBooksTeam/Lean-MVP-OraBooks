import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  root: 'src',
  publicDir: '../public',
  build: {
    outDir: '../../dist',
    emptyOutDir: true,
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
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
});
