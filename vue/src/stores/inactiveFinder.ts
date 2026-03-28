import { defineStore } from 'pinia'

const COORD_MIN = -400
const COORD_MAX = 400

export const useInactiveFinderStore = defineStore('inactiveFinder', {
  state: () => ({
    serverId: null as number | null,
    coordX: null as number | null,
    coordY: null as number | null,
    page: 1,
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
  },
})
