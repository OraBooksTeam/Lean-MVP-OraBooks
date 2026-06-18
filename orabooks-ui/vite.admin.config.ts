import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

/** Single-file admin bundle for WordPress (no runtime chunk fetches). */
export default defineConfig({
  base: './',
  plugins: [react(), tailwindcss()],
  root: 'src',
  build: {
    outDir: path.resolve(__dirname, '../OraBooks Lean MVP/assets/react'),
    emptyOutDir: false,
    cssCodeSplit: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/pages/admin/main.tsx'),
      output: {
        entryFileNames: 'admin.js',
        inlineDynamicImports: true,
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
