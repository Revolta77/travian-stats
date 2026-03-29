import { defineStore } from 'pinia'
import type { AppLocale } from '../lib/uiStorage'
import { readSavedDarkMode, readSavedLocale, syncDarkClassOnHtml } from '../lib/uiStorage'

export const useUiStore = defineStore('ui', {
  state: () => ({
    darkMode: readSavedDarkMode(),
    locale: readSavedLocale(),
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
    setLocale(value: AppLocale) {
      if (this.locale === value) {
        return
      }
      this.locale = value
    },
  },
})
