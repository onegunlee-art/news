export type DiscoveryCategory = 'geopolitics' | 'business' | 'tech' | 'climate' | 'other'

export interface DiscoveryBriefing {
  what_changed: string
  why_changed: string
  why_important: string
  future_impact: string
  highlights?: string[]
}

export interface DiscoverySource {
  id?: number
  name: string
  url: string
  article_title?: string | null
  verified?: boolean
  fail_reason?: string | null
}

export interface DiscoveryPollStats {
  total: number
  counts: number[]
  percents: number[]
  is_dummy?: boolean
}

export interface DiscoveryPollViewer {
  has_voted: boolean
  option_idx: number | null
}

export interface DiscoveryPoll {
  id?: number
  question: string
  options: string[]
  stats?: DiscoveryPollStats
  viewer?: DiscoveryPollViewer
}

export interface DiscoveryChange {
  id: number
  edition_id: number
  rank: number
  category: DiscoveryCategory
  title: string
  summary: string
  briefing: DiscoveryBriefing
  status: string
  sources: DiscoverySource[]
  poll: DiscoveryPoll | null
  edition_date?: string
}

export interface DiscoveryEdition {
  id: number
  edition_date: string
  status: 'generating' | 'draft' | 'published'
  published_at: string | null
  change_count: number
  warning_message: string | null
}

export type PublicTab = 'home' | 'community' | 'search' | 'mypage'
export type SearchView = 'home' | 'results' | 'filter'
