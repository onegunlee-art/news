import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export interface AudioListItem {
  id: number
  title: string
  description?: string | null
  source?: string | null
  listenedAt: string // ISO date
}

interface AudioListState {
  items: AudioListItem[]
  addItem: (item: Omit<AudioListItem, 'listenedAt'>) => void
  removeItem: (id: number) => void
  clearList: () => void
}

const MAX_ITEMS = 100

export const useAudioListStore = create<AudioListState>()(
  persist(
    (set) => ({
      items: [],

      addItem: (item) => {
        const newEntry: AudioListItem = {
          ...item,
          listenedAt: new Date().toISOString(),
        }
        set((state) => {
          const filtered = state.items.filter((i) => i.id !== newEntry.id)
          const next = [newEntry, ...filtered].slice(0, MAX_ITEMS)
          return { items: next }
        })
      },

      removeItem: (id) => {
        set((state) => ({
          items: state.items.filter((i) => i.id !== id),
        }))
      },

      clearList: () => set({ items: [] }),
    }),
    { name: 'audio-list-storage' }
  )
)
