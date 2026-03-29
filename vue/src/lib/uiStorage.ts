/** Kľúč zdieľaný s `piniaPersistLocalStorage` pre ukladanie UI preferencií. */
export const UI_STORAGE_KEY = 'travian_vue_ui_v1'

export const APP_LOCALES = ['en', 'de', 'sk'] as const

export type AppLocale = (typeof APP_LOCALES)[number]

export function isAppLocale(v: unknown): v is AppLocale {
  return typeof v === 'string' && (APP_LOCALES as readonly string[]).includes(v)
}

export function readSavedDarkMode(): boolean {
  try {
    const raw = localStorage.getItem(UI_STORAGE_KEY)
    if (!raw) {
      return false
    }
    const o = JSON.parse(raw) as { darkMode?: unknown }
    return o.darkMode === true
  } catch {
    return false
  }
}

/** Predvolený jazyk: angličtina. */
export function readSavedLocale(): AppLocale {
  try {
    const raw = localStorage.getItem(UI_STORAGE_KEY)
    if (!raw) {
      return 'en'
    }
    const o = JSON.parse(raw) as { locale?: unknown }
    if (isAppLocale(o.locale)) {
      return o.locale
    }
  } catch {
    /* ignore */
  }
  return 'en'
}

export function syncDarkClassOnHtml(dark: boolean): void {
  document.documentElement.classList.toggle('dark', dark)
}

/** Pred prvým vykreslením – aby neblikol svetlý motív. */
export function applyStoredDarkModeClass(): void {
  syncDarkClassOnHtml(readSavedDarkMode())
}
