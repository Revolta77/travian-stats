import { createI18n } from 'vue-i18n'
import de from './locales/de'
import en from './locales/en'
import sk from './locales/sk'
import { readSavedLocale } from './lib/uiStorage'

export const i18n = createI18n({
  legacy: false,
  locale: readSavedLocale(),
  fallbackLocale: 'en',
  messages: {
    en,
    de,
    sk,
  },
})
