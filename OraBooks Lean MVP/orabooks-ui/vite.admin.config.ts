import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

const configDir = process.env.ORABOOKS_UI_ROOT || process.cwd();
const pluginAssetsDir = process.env.ORABOOKS_PLUGIN_ASSETS || path.resolve(configDir, '..', 'assets', 'react');

/** Single-file admin bundle for WordPress (no runtime chunk fetches). */
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
    outDir: pluginAssetsDir,
    emptyOutDir: false,
    cssCodeSplit: false,
    codeSplitting: false,
    modulePreload: false,
    rollupOptions: {
      input: path.resolve(configDir, 'src/pages/admin/main.tsx'),
      output: {
        format: 'iife',
        name: 'OraBooksAdmin',
        inlineDynamicImports: true,
        entryFileNames: 'admin.js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(configDir, 'src'),
    },
  },
});
