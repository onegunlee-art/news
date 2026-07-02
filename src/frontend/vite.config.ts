import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'
import fs from 'fs'

function versionJsonPlugin() {
  const buildVersion = Date.now()
  return {
    name: 'version-json',
    config() {
      return {
        define: {
          __APP_BUILD_VERSION__: JSON.stringify(buildVersion),
        },
      }
    },
    closeBundle() {
      fs.writeFileSync(
        path.resolve(__dirname, '../../public/version.json'),
        JSON.stringify({ v: buildVersion }),
      )
    },
  }
}

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    react(),
    versionJsonPlugin(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'https://www.thegist.co.kr',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: '../../public',
    emptyOutDir: false,
    sourcemap: false,
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['react', 'react-dom', 'react-router-dom'],
          ui: ['framer-motion', 'clsx'],
        },
      },
    },
  },
})
