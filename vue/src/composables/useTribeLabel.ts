import { useI18n } from 'vue-i18n'

/** Travian tribe id → i18n key under `travianTribes` (must match `config/travian.php`). */
export function tribeLabelI18nKey(id: number): string {
  return `travianTribes.${id}`
}

export function useTribeLabel() {
  const { t, te } = useI18n()

  function tribeLabel(id: number, apiFallback?: string): string {
    const key = tribeLabelI18nKey(id)
    if (te(key)) {
      return t(key)
    }
    if (apiFallback !== undefined && apiFallback !== '') {
      return apiFallback
    }
    return t('common.emDash')
  }

  return { tribeLabel }
}
