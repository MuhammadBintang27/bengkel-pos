import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import Components from 'unplugin-vue-components/vite'
import { PrimeVueResolver } from '@primevue/auto-import-resolver'

const backend = process.env.BACKEND_URL || 'http://127.0.0.1:8000'

export default defineConfig(({ command }) => ({
  // Saat build, aset dilayani Laravel dari public/spa.
  base: command === 'build' ? '/spa/' : '/',
  plugins: [vue(), tailwindcss(), Components({ resolvers: [PrimeVueResolver()], dts: false })],
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
  server: {
    proxy: {
      '/api': { target: backend, changeOrigin: false },
      '/sanctum': { target: backend, changeOrigin: false },
    },
  },
  build: { outDir: '../backend/public/spa', emptyOutDir: true, chunkSizeWarningLimit: 1200 },
  test: { environment: 'node' },
}))
