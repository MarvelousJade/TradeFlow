import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
  plugins: [react()],
  root: fileURLToPath(
    new URL('./wordpress/wp-content/themes/tradeflow/assets/src', import.meta.url),
  ),
  publicDir: false,
  build: {
    outDir: fileURLToPath(
      new URL('./wordpress/wp-content/themes/tradeflow/dist', import.meta.url),
    ),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: fileURLToPath(
        new URL(
          './wordpress/wp-content/themes/tradeflow/assets/src/main.tsx',
          import.meta.url,
        ),
      ),
      output: {
        entryFileNames: 'assets/booking-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./test-setup.ts'],
    include: ['**/*.test.{ts,tsx}'],
    css: true,
  },
});

