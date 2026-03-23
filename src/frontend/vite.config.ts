import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'
import fs from 'fs'

function versionJsonPlugin() {
  return {
    name: 'version-json',
    closeBundle() {
      const version = { v: Date.now() }
      fs.writeFileSync(
        path.resolve(__dirname, '../../public/version.json'),
        JSON.stringify(version),
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
