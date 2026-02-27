import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export type FontSize = 'small' | 'normal' | 'large'
export type Theme = 'light' | 'dark'

interface ViewSettingsState {
  fontSize: FontSize
  theme: Theme
  setFontSize: (size: FontSize) => void
  setTheme: (theme: Theme) => void
}

export const useViewSettingsStore = create<ViewSettingsState>()(
  persist(
    (set) => ({
      fontSize: 'normal',
      theme: 'light',
      setFontSize: (size) => set({ fontSize: size }),
      setTheme: (theme) => set({ theme }),
    }),
    { name: 'view-settings' }
  )
)
