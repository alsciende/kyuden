import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// served behind FrankenPHP (localhost:8080), which sends /api and the development console to Symfony
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    hmr: {
      clientPort: 8080,
    },
    // the sources are a bind mount: file events from the host do not always reach the container
    watch: {
      usePolling: true,
    },
  },
})
