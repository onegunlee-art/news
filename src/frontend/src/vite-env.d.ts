/// <reference types="vite/client" />

declare const __APP_BUILD_VERSION__: number

interface ImportMetaEnv {
  readonly VITE_API_URL: string
  readonly VITE_ENABLE_DISCOVERY?: string
  readonly VITE_ENABLE_DISCOVERY_PUBLIC?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
