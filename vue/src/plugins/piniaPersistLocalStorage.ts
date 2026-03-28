import type { PiniaPluginContext } from 'pinia'
import { UI_STORAGE_KEY } from '../lib/uiStorage'

const COORD_MIN = -400
const COORD_MAX = 400

const VILLAGE_STATS_KEY = 'travian_vue_village_stats_v1'
const USER_STATS_KEY = 'travian_vue_user_stats_v1'
const ALLIANCE_STATS_KEY = 'travian_vue_alliance_stats_v1'
const INACTIVE_FINDER_KEY = 'travian_vue_inactive_finder_v1'

const MAX_STR = 200

function clampInt(n: unknown, min: number, max: number): number | null {
  if (typeof n !== 'number' || !Number.isInteger(n)) {
    return null
  }
  if (n < min || n > max) {
    return null
  }
  return n
}

function asTrimmedString(s: unknown, max = MAX_STR): string {
  if (typeof s !== 'string') {
    return ''
  }
  return s.length > max ? s.slice(0, max) : s
}

function reviveVillageStats(raw: unknown): Record<string, unknown> | null {
  if (!raw || typeof raw !== 'object') {
    return null
  }
  const o = raw as Record<string, unknown>
  const patch: Record<string, unknown> = {}

  if ('serverId' in o && (typeof o.serverId === 'number' || o.serverId === null)) {
    patch.serverId = o.serverId
  }

  if ('coordX' in o) {
    if (o.coordX === null) {
      patch.coordX = null
    } else if (
      typeof o.coordX === 'number' &&
      Number.isInteger(o.coordX) &&
      o.coordX >= COORD_MIN &&
      o.coordX <= COORD_MAX
    ) {
      patch.coordX = o.coordX
    }
  }
  if ('coordY' in o) {
    if (o.coordY === null) {
      patch.coordY = null
    } else if (
      typeof o.coordY === 'number' &&
      Number.isInteger(o.coordY) &&
      o.coordY >= COORD_MIN &&
      o.coordY <= COORD_MAX
    ) {
      patch.coordY = o.coordY
    }
  }

  if ('page' in o) {
    const page = clampInt(o.page, 1, 5)
    if (page !== null) {
      patch.page = page
    }
  }

  if ('excludeMyAccount' in o && typeof o.excludeMyAccount === 'boolean') {
    patch.excludeMyAccount = o.excludeMyAccount
  }
  if ('myAccountName' in o) {
    patch.myAccountName = asTrimmedString(o.myAccountName)
  }
  if ('tableAccountFilter' in o) {
    patch.tableAccountFilter = asTrimmedString(o.tableAccountFilter)
  }
  if ('tableVillageFilter' in o) {
    patch.tableVillageFilter = asTrimmedString(o.tableVillageFilter)
  }
  if ('tableAllianceFilter' in o) {
    patch.tableAllianceFilter = asTrimmedString(o.tableAllianceFilter)
  }
  if ('tableTribeId' in o) {
    const tid = o.tableTribeId
    if (tid === null || (typeof tid === 'number' && Number.isInteger(tid) && tid >= 1 && tid <= 99)) {
      patch.tableTribeId = tid
    }
  }

  return Object.keys(patch).length ? patch : null
}

function reviveInactiveFinder(raw: unknown): Record<string, unknown> | null {
  if (!raw || typeof raw !== 'object') {
    return null
  }
  const o = raw as Record<string, unknown>
  const patch: Record<string, unknown> = {}

  if ('serverId' in o && (typeof o.serverId === 'number' || o.serverId === null)) {
    patch.serverId = o.serverId
  }

  if ('coordX' in o) {
    if (o.coordX === null) {
      patch.coordX = null
    } else if (
      typeof o.coordX === 'number' &&
      Number.isInteger(o.coordX) &&
      o.coordX >= COORD_MIN &&
      o.coordX <= COORD_MAX
    ) {
      patch.coordX = o.coordX
    }
  }
  if ('coordY' in o) {
    if (o.coordY === null) {
      patch.coordY = null
    } else if (
      typeof o.coordY === 'number' &&
      Number.isInteger(o.coordY) &&
      o.coordY >= COORD_MIN &&
      o.coordY <= COORD_MAX
    ) {
      patch.coordY = o.coordY
    }
  }

  if ('page' in o) {
    const page = clampInt(o.page, 1, 5)
    if (page !== null) {
      patch.page = page
    }
  }

  return Object.keys(patch).length ? patch : null
}

function reviveUserStats(raw: unknown): Record<string, unknown> | null {
  if (!raw || typeof raw !== 'object') {
    return null
  }
  const o = raw as Record<string, unknown>
  const patch: Record<string, unknown> = {}

  if ('serverId' in o && (typeof o.serverId === 'number' || o.serverId === null)) {
    patch.serverId = o.serverId
  }

  if ('coordX' in o) {
    if (o.coordX === null) {
      patch.coordX = null
    } else if (
      typeof o.coordX === 'number' &&
      Number.isInteger(o.coordX) &&
      o.coordX >= COORD_MIN &&
      o.coordX <= COORD_MAX
    ) {
      patch.coordX = o.coordX
    }
  }
  if ('coordY' in o) {
    if (o.coordY === null) {
      patch.coordY = null
    } else if (
      typeof o.coordY === 'number' &&
      Number.isInteger(o.coordY) &&
      o.coordY >= COORD_MIN &&
      o.coordY <= COORD_MAX
    ) {
      patch.coordY = o.coordY
    }
  }

  if ('page' in o) {
    const page = clampInt(o.page, 1, 5)
    if (page !== null) {
      patch.page = page
    }
  }

  if (
    'sortBy' in o &&
    (o.sortBy === 'distance' ||
      o.sortBy === 'account' ||
      o.sortBy === 'population' ||
      o.sortBy === 'villages')
  ) {
    patch.sortBy = o.sortBy
  }
  if ('sortDir' in o && (o.sortDir === 'asc' || o.sortDir === 'desc')) {
    patch.sortDir = o.sortDir
  }

  if ('tableAccountFilter' in o) {
    patch.tableAccountFilter = asTrimmedString(o.tableAccountFilter)
  }
  if ('tableAllianceFilter' in o) {
    patch.tableAllianceFilter = asTrimmedString(o.tableAllianceFilter)
  }

  return Object.keys(patch).length ? patch : null
}

function reviveAllianceStats(raw: unknown): Record<string, unknown> | null {
  if (!raw || typeof raw !== 'object') {
    return null
  }
  const o = raw as Record<string, unknown>
  const patch: Record<string, unknown> = {}

  if ('serverId' in o && (typeof o.serverId === 'number' || o.serverId === null)) {
    patch.serverId = o.serverId
  }

  if ('page' in o) {
    const page = clampInt(o.page, 1, 5)
    if (page !== null) {
      patch.page = page
    }
  }

  if ('tagFilter' in o) {
    patch.tagFilter = asTrimmedString(o.tagFilter)
  }

  return Object.keys(patch).length ? patch : null
}

function loadJson(key: string): unknown {
  try {
    const s = localStorage.getItem(key)
    if (!s) {
      return null
    }
    return JSON.parse(s) as unknown
  } catch {
    return null
  }
}

export function piniaPersistLocalStorage(ctx: PiniaPluginContext): void {
  const { store } = ctx

  if (store.$id === 'villageStats') {
    const patch = reviveVillageStats(loadJson(VILLAGE_STATS_KEY))
    if (patch) {
      store.$patch(patch as Record<string, never>)
    }
    store.$subscribe(
      (_mutation, state) => {
        localStorage.setItem(
          VILLAGE_STATS_KEY,
          JSON.stringify({
            serverId: state.serverId,
            coordX: state.coordX,
            coordY: state.coordY,
            page: state.page,
            excludeMyAccount: state.excludeMyAccount,
            myAccountName: state.myAccountName,
            tableAccountFilter: state.tableAccountFilter,
            tableVillageFilter: state.tableVillageFilter,
            tableAllianceFilter: state.tableAllianceFilter,
            tableTribeId: state.tableTribeId,
          }),
        )
      },
      { detached: true },
    )
    return
  }

  if (store.$id === 'userStats') {
    const patch = reviveUserStats(loadJson(USER_STATS_KEY))
    if (patch) {
      store.$patch(patch as Record<string, never>)
    }
    store.$subscribe(
      (_mutation, state) => {
        localStorage.setItem(
          USER_STATS_KEY,
          JSON.stringify({
            serverId: state.serverId,
            coordX: state.coordX,
            coordY: state.coordY,
            page: state.page,
            sortBy: state.sortBy,
            sortDir: state.sortDir,
            tableAccountFilter: state.tableAccountFilter,
            tableAllianceFilter: state.tableAllianceFilter,
          }),
        )
      },
      { detached: true },
    )
    return
  }

  if (store.$id === 'allianceStats') {
    const patch = reviveAllianceStats(loadJson(ALLIANCE_STATS_KEY))
    if (patch) {
      store.$patch(patch as Record<string, never>)
    }
    store.$subscribe(
      (_mutation, state) => {
        localStorage.setItem(
          ALLIANCE_STATS_KEY,
          JSON.stringify({
            serverId: state.serverId,
            tagFilter: state.tagFilter,
            page: state.page,
          }),
        )
      },
      { detached: true },
    )
    return
  }

  if (store.$id === 'inactiveFinder') {
    const patch = reviveInactiveFinder(loadJson(INACTIVE_FINDER_KEY))
    if (patch) {
      store.$patch(patch as Record<string, never>)
    }
    store.$subscribe(
      (_mutation, state) => {
        localStorage.setItem(
          INACTIVE_FINDER_KEY,
          JSON.stringify({
            serverId: state.serverId,
            coordX: state.coordX,
            coordY: state.coordY,
            page: state.page,
          }),
        )
      },
      { detached: true },
    )
    return
  }

  if (store.$id === 'ui') {
    store.$subscribe(
      (_mutation, state) => {
        localStorage.setItem(UI_STORAGE_KEY, JSON.stringify({ darkMode: state.darkMode }))
      },
      { detached: true },
    )
  }
}
