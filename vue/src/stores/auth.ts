import { defineStore } from 'pinia'
import { api, getAdminToken, setAdminToken } from '../lib/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: getAdminToken() as string | null,
  }),
  getters: {
    isLoggedIn: (s) => !!s.token,
  },
  actions: {
    syncFromStorage() {
      this.token = getAdminToken()
    },
    setToken(t: string | null) {
      this.token = t
      setAdminToken(t)
    },
    async login(email: string, password: string) {
      const { data } = await api.post<{ token: string }>('/admin/login', { email, password })
      this.setToken(data.token)
    },
    async logout() {
      try {
        await api.post('/admin/logout')
      } catch {
        /* ignore */
      }
      this.setToken(null)
    },
  },
})
