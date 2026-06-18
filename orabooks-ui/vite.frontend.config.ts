import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
  base: './',
  plugins: [react(), tailwindcss()],
  root: 'src',
  build: {
    outDir: path.resolve(__dirname, '../OraBooks Lean MVP/assets/react'),
    emptyOutDir: false,
    cssCodeSplit: false,
    codeSplitting: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/pages/frontend/main.tsx'),
      output: {
        entryFileNames: 'frontend.js',
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
