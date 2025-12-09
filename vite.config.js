import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';

export default defineConfig({
  plugins: [preact()],
  build: {
    outDir: 'js/dist',
    emptyOutDir: true,
    lib: {
      entry: 'src/animateur/main.jsx',
      name: 'MjAnimateurDashboard',
      fileName: 'animateur-account',
      formats: ['iife']
    },
    rollupOptions: {
      output: {
        entryFileNames: 'animateur-account.js',
        assetFileNames: 'animateur-account.[ext]'
      }
    }
  }
});
