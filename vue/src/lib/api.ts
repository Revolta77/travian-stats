import axios from 'axios'

const raw = import.meta.env.VITE_API_BASE as string | undefined
const baseURL = raw && raw !== '' ? raw.replace(/\/$/, '') : '/api'

/** Základná cesta k API (relatívna `/api` alebo plná URL z `VITE_API_BASE`) — pre `fetch` a streamy. */
export function getApiRoot(): string {
  return baseURL
}

export const ADMIN_TOKEN_KEY = 'travian_admin_token'

export const api = axios.create({
  baseURL,
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const t = localStorage.getItem(ADMIN_TOKEN_KEY)
  if (t) {
    config.headers.Authorization = `Bearer ${t}`
  }
  return config
})

api.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem(ADMIN_TOKEN_KEY)
      const path = window.location.pathname
      if (!path.includes('/login')) {
        const base = (import.meta.env.BASE_URL || '/').replace(/\/$/, '')
        window.location.assign(base ? `${base}/login` : '/login')
      }
    }
    return Promise.reject(err)
  },
)

export function setAdminToken(token: string | null): void {
  if (token) {
    localStorage.setItem(ADMIN_TOKEN_KEY, token)
  } else {
    localStorage.removeItem(ADMIN_TOKEN_KEY)
  }
}

export function getAdminToken(): string | null {
  return localStorage.getItem(ADMIN_TOKEN_KEY)
}

export type ServerOption = {
  id: number
  name: string
  slug: string
}

export type AdminServer = {
  id: number
  name: string
  slug: string
  base_url: string | null
  timezone: string
  is_active: boolean
  created_at?: string
  updated_at?: string
}

export type TribeOption = {
  id: number
  label: string
}

export type VillageStatsMeta = {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export type VillageStatsRow = {
  village_id: number
  distance: number
  account: {
    name: string
    total_population: number
    village_count: number
  }
  village: {
    name: string
    x: number
    y: number
    population: number | null
    tribe: number
    tribe_label: string
    days_without_change: number
  }
  alliance: {
    tag: string | null
  }
  daily_changes: Record<string, number | null | undefined>
  actions: null
}

export type VillageStatsResponse = {
  date_columns: string[]
  rows: VillageStatsRow[]
  meta: VillageStatsMeta
}

/** Rovnaký tvar ako village stats; odpoveď z `/inactive-finder`. */
export type InactiveFinderResponse = VillageStatsResponse

export type UserStatsMeta = VillageStatsMeta & {
  has_coordinates: boolean
}

export type UserStatsRow = {
  player_id: number
  distance: number | null
  village_count: number
  account: {
    name: string
    total_population: number
  }
  alliance: {
    tag: string | null
  }
  daily_changes: Record<string, number | null | undefined>
}

export type UserStatsResponse = {
  date_columns: string[]
  rows: UserStatsRow[]
  meta: UserStatsMeta
}

export type AllianceStatsRow = {
  alliance_id: number
  tag: string
  member_count: number
  village_count: number
  total_population: number
  daily_changes: Record<string, number | null | undefined>
}

export type AllianceStatsResponse = {
  date_columns: string[]
  rows: AllianceStatsRow[]
  meta: VillageStatsMeta
}
