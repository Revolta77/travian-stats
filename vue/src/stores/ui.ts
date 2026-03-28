import { defineStore } from 'pinia'
import { readSavedDarkMode, syncDarkClassOnHtml } from '../lib/uiStorage'

export const useUiStore = defineStore('ui', {
  state: () => ({
    darkMode: readSavedDarkMode(),
  }),
  actions: {
    setDarkMode(value: boolean) {
      if (this.darkMode === value) {
        return
      }
      this.darkMode = value
      syncDarkClassOnHtml(value)
    },
    toggleDarkMode() {
      this.setDarkMode(!this.darkMode)
    },
  },
})
