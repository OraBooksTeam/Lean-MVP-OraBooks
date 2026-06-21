import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
  base: './',
  plugins: [react(), tailwindcss()],
  root: 'src',
  define: {
    'import.meta.url': '""',
    'import.meta.env.MODE': JSON.stringify('production'),
    'import.meta.env.PROD': 'true',
    'import.meta.env.DEV': 'false',
  },
  build: {
    outDir: path.resolve(__dirname, '../OraBooks Lean MVP/assets/react'),
    emptyOutDir: false,
    cssCodeSplit: false,
    codeSplitting: false,
    modulePreload: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/pages/frontend/main.tsx'),
      output: {
        format: 'iife',
        name: 'OraBooksFrontend',
        inlineDynamicImports: true,
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
