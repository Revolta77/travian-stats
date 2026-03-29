import { defineStore } from 'pinia'

const COORD_MIN = -400
const COORD_MAX = 400

export type VillageStatsSortBy = 'distance' | 'population' | 'account' | 'village' | 'alliance'

export type VillageStatsSortDir = 'asc' | 'desc'

export const useVillageStatsStore = defineStore('villageStats', {
  state: () => ({
    serverId: null as number | null,
    contextPlayerId: null as number | null,
    contextAllianceId: null as number | null,
    coordX: null as number | null,
    coordY: null as number | null,
    sortBy: 'distance' as VillageStatsSortBy,
    sortDir: 'asc' as VillageStatsSortDir,
    page: 1,
    excludeMyAccount: false,
    myAccountName: '',
    tableAccountFilter: '',
    tableVillageFilter: '',
    tableAllianceFilter: '',
    tableTribeId: null as number | null,
  }),
  getters: {
    coordsValid(): boolean {
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
    coordsOptionalValid(): boolean {
      if (this.coordX === null && this.coordY === null) {
        return true
      }
      return this.coordsValid
    },
    hasCoordsForSort(): boolean {
      return this.coordsValid
    },
  },
  actions: {
    setServerId(id: number | null) {
      this.serverId = id
    },
    setCoordX(v: number | null) {
      this.coordX = v
    },
    setCoordY(v: number | null) {
      this.coordY = v
    },
    setPage(p: number) {
      this.page = Math.min(5, Math.max(1, p))
    },
    resetPage() {
      this.page = 1
    },
    setSortBy(v: VillageStatsSortBy) {
      this.sortBy = v
    },
    setSortDir(v: VillageStatsSortDir) {
      this.sortDir = v
    },
    ensureSortCompatibleWithCoords() {
      if (!this.hasCoordsForSort && this.sortBy === 'distance') {
        this.sortBy = 'population'
        this.sortDir = 'desc'
      }
    },
    setExcludeMyAccount(v: boolean) {
      this.excludeMyAccount = v
    },
    setMyAccountName(s: string) {
      this.myAccountName = s
    },
    setTableAccountFilter(s: string) {
      this.tableAccountFilter = s
    },
    setTableVillageFilter(s: string) {
      this.tableVillageFilter = s
    },
  },
})
