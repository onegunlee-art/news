export type DiscoveryCategory = 'geopolitics' | 'business' | 'tech' | 'climate' | 'other'

export interface DiscoveryBriefing {
  what_changed: string
  why_changed: string
  why_important: string
  future_impact: string
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

export interface DiscoveryPoll {
  id?: number
  question: string
  options: string[]
  stats?: DiscoveryPollStats
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
}

export interface DiscoveryEdition {
  id: number
  edition_date: string
  status: 'generating' | 'draft' | 'published'
  published_at: string | null
  change_count: number
  warning_message: string | null
}

export interface DiscoveryRun {
  id: number
  edition_date: string
  generated_count: number
  discarded_count: number
  reasons_json: string | null
  duration_sec: number | null
  run_at: string
}

export type PreviewTab = 'home' | 'community' | 'search' | 'mypage'
export type PreviewScreen = 'home' | 'changeDetail' | 'pollDetail'
