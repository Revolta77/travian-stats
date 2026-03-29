import { defineStore } from 'pinia'

const COORD_MIN = -400
const COORD_MAX = 400

export type UserStatsSortBy = 'distance' | 'account' | 'population' | 'villages'

export type UserStatsSortDir = 'asc' | 'desc'

export const useUserStatsStore = defineStore('userStats', {
  state: () => ({
    serverId: null as number | null,
    contextPlayerId: null as number | null,
    contextAllianceId: null as number | null,
    coordX: null as number | null,
    coordY: null as number | null,
    page: 1,
    sortBy: 'population' as UserStatsSortBy,
    sortDir: 'desc' as UserStatsSortDir,
    tableAccountFilter: '',
    tableAllianceFilter: '',
  }),
  getters: {
    /** Obe súradnice prázdne alebo obe platné celé čísla v rozsahu. */
    coordsOptionalValid(): boolean {
      if (this.coordX === null && this.coordY === null) {
        return true
      }
      return (
        this.coordX !== null &&
        this.coordY !== null &&
        Number.isInteger(this.coordX) &&
        Number.isInteger(this.coordY) &&
        this.coordX >= COORD_MIN &&
        this.coordX <= COORD_MAX &&
        this.coordY >= COORD_MIN &&
        this.coordY <= COORD_MAX
      )
    },
    /** Má zmysel zoraďovanie podľa vzdialenosti (API očakáva X a Y). */
    hasCoordsForSort(): boolean {
      return (
        this.coordX !== null &&
        this.coordY !== null &&
        Number.isInteger(this.coordX) &&
        Number.isInteger(this.coordY) &&
        this.coordX >= COORD_MIN &&
        this.coordX <= COORD_MAX &&
        this.coordY >= COORD_MIN &&
        this.coordY <= COORD_MAX
      )
    },
  },
  actions: {
    setPage(p: number) {
      this.page = Math.min(5, Math.max(1, p))
    },
    resetPage() {
      this.page = 1
    },
    setSortBy(v: UserStatsSortBy) {
      this.sortBy = v
    },
    setSortDir(v: UserStatsSortDir) {
      this.sortDir = v
    },
    ensureSortCompatibleWithCoords() {
      if (!this.hasCoordsForSort && this.sortBy === 'distance') {
        this.sortBy = 'population'
        this.sortDir = 'desc'
      }
    },
  },
})
