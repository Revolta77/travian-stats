/** Kľúč zdieľaný s `piniaPersistLocalStorage` pre ukladanie UI preferencií. */
export const UI_STORAGE_KEY = 'travian_vue_ui_v1'

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

export function syncDarkClassOnHtml(dark: boolean): void {
  document.documentElement.classList.toggle('dark', dark)
}

/** Pred prvým vykreslením – aby neblikol svetlý motív. */
export function applyStoredDarkModeClass(): void {
  syncDarkClassOnHtml(readSavedDarkMode())
}
