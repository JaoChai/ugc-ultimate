import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    rollupOptions: {
      output: {
        // Manual chunks for better caching and smaller initial bundle
        manualChunks: {
          // React core - changes rarely
          'vendor-react': ['react', 'react-dom', 'react-router-dom'],
          // UI components - changes occasionally
          'vendor-ui': [
            '@radix-ui/react-dialog',
            '@radix-ui/react-select',
            '@radix-ui/react-tabs',
            '@radix-ui/react-label',
            '@radix-ui/react-progress',
            '@radix-ui/react-slider',
            '@radix-ui/react-switch',
          ],
          // Real-time communication - separate chunk
          'vendor-realtime': ['laravel-echo', 'pusher-js'],
        },
      },
    },
    // Increase chunk size warning limit (default 500KB)
    chunkSizeWarningLimit: 600,
  },
})
