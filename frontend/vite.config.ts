import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [vue(), tailwindcss()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      // Without an explicit host Node resolves `localhost` to ::1 and Vite
      // binds IPv6 loopback only — which refuses the documented
      // http://127.0.0.1:5173 origin. Loopback keeps it off the LAN too.
      host: '127.0.0.1',
      port: 5173,
      proxy: {
        // The dev server proxies the API so the browser talks to a single
        // origin and CORS never comes into play during development.
        '/api': {
          target: env.VITE_API_PROXY_TARGET ?? 'http://127.0.0.1:8010',
          changeOrigin: true,
        },
      },
    },
    build: {
      target: 'es2022',
      sourcemap: false,
      rollupOptions: {
        output: {
          manualChunks(id: string) {
            if (id.includes('node_modules')) {
              return 'vendor'
            }

            return undefined
          },
        },
      },
    },
  }
})
