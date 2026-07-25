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
    modulePreload: {
      resolveDependencies: (_filename, deps) =>
        deps.filter((dep) => !dep.includes('discovery-admin') && !dep.includes('discovery-public')),
    },
    rollupOptions: {
      output: {
        manualChunks(id) {
          const normalized = id.replace(/\\/g, '/')
          if (normalized.includes('/src/discovery/')) {
            return 'discovery-public'
          }
          if (normalized.includes('/components/Admin/Discovery/')) {
            return 'discovery-admin'
          }
          if (id.includes('node_modules/react') || id.includes('node_modules/react-dom') || id.includes('node_modules/react-router-dom')) {
            return 'vendor'
          }
          if (id.includes('node_modules/framer-motion') || id.includes('node_modules/clsx')) {
            return 'ui'
          }
        },
      },
    },
  },
})
