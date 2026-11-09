import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import { VitePWA } from 'vite-plugin-pwa'

// served behind FrankenPHP (localhost:8080), which sends /api to Symfony
export default defineConfig({
  plugins: [
    vue(),
    // installable (manifest, service worker): only in the build, not on the Vite server
    VitePWA({
      // a new release takes over at once and reloads the open pages: the game is read again from the API
      registerType: 'autoUpdate',
      includeAssets: ['favicon.svg', 'icons/apple-touch-icon.png'],
      manifest: {
        name: 'Kyūden',
        short_name: 'Kyūden',
        description: 'Play Legend of the Five Rings in your browser.',
        lang: 'en',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        background_color: '#f4eee2',
        theme_color: '#f4eee2',
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
          {
            src: '/icons/icon-maskable-512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        // the shell of the client only: the fonts, the images and the cards are cached by the browser as usual
        globPatterns: ['**/*.{js,css,html}'],
        globIgnores: ['architecture.html'],
        cleanupOutdatedCaches: true,
        // a route of the client opens offline; what Symfony and the hub serve never comes from the cache
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//, /^\/\.well-known\//, /^\/bundles\//, /^\/architecture\.html$/],
      },
    }),
  ],
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
