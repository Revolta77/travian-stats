import { defineStore } from 'pinia'

export const useAllianceStatsStore = defineStore('allianceStats', {
  state: () => ({
    serverId: null as number | null,
    tagFilter: '',
    page: 1,
  }),
  actions: {
    setPage(p: number) {
      this.page = Math.min(5, Math.max(1, p))
    },
    resetPage() {
      this.page = 1
    },
  },
})
