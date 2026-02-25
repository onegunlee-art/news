import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export type FontSize = 'small' | 'normal' | 'large'
export type Theme = 'light' | 'dark'

interface ViewSettingsState {
  fontSize: FontSize
  grayscale: boolean
  theme: Theme
  setFontSize: (size: FontSize) => void
  setGrayscale: (on: boolean) => void
  toggleGrayscale: () => void
  setTheme: (theme: Theme) => void
}

export const useViewSettingsStore = create<ViewSettingsState>()(
  persist(
    (set) => ({
      fontSize: 'normal',
      grayscale: false,
      theme: 'light',
      setFontSize: (size) => set({ fontSize: size }),
      setGrayscale: (on) => set({ grayscale: on }),
      toggleGrayscale: () => set((s) => ({ grayscale: !s.grayscale })),
      setTheme: (theme) => set({ theme }),
    }),
    { name: 'view-settings' }
  )
)
