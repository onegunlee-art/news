/// <reference types="vite/client" />

declare const __APP_BUILD_VERSION__: number

interface ImportMetaEnv {
  readonly VITE_API_URL: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
