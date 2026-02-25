import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export type FontSize = 'small' | 'normal' | 'large'

interface ViewSettingsState {
  fontSize: FontSize
  grayscale: boolean
  setFontSize: (size: FontSize) => void
  setGrayscale: (on: boolean) => void
  toggleGrayscale: () => void
}

export const useViewSettingsStore = create<ViewSettingsState>()(
  persist(
    (set) => ({
      fontSize: 'normal',
      grayscale: false,
      setFontSize: (size) => set({ fontSize: size }),
      setGrayscale: (on) => set({ grayscale: on }),
      toggleGrayscale: () => set((s) => ({ grayscale: !s.grayscale })),
    }),
    { name: 'view-settings' }
  )
)
