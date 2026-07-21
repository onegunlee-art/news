/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'
import fs from 'fs'

function versionJsonPlugin() {
  const buildVersion = Date.now()
  const publicDir = path.resolve(__dirname, '../../public')

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
        path.join(publicDir, 'version.json'),
        JSON.stringify({ v: buildVersion }),
      )

      const swPath = path.join(publicDir, 'sw.js')
      let swSource = fs.readFileSync(swPath, 'utf8')
      const cachePlaceholder = "'gist-assets-__BUILD_VERSION__'"
      const cacheLinePattern = /const ASSETS_CACHE = 'gist-assets-[^']+'/
      const cacheValue = `'gist-assets-${buildVersion}'`

      if (swSource.includes(cachePlaceholder)) {
        swSource = swSource.replace(cachePlaceholder, cacheValue)
      } else if (cacheLinePattern.test(swSource)) {
        swSource = swSource.replace(cacheLinePattern, `const ASSETS_CACHE = ${cacheValue}`)
      } else {
        throw new Error(
          'public/sw.js must contain gist-assets-__BUILD_VERSION__ placeholder for deploy cache busting',
        )
      }
      fs.writeFileSync(swPath, swSource)
    },
  }
}

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    react(),
    versionJsonPlugin(),
  ],
  test: {
    environment: 'node',
    include: ['src/**/*.test.ts'],
  },
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
