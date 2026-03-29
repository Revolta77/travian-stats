import type { ComposerTranslation } from 'vue-i18n'
import type { RouteLocationNormalizedLoaded } from 'vue-router'

export function applyDocumentTitle(route: RouteLocationNormalizedLoaded, t: ComposerTranslation): void {
  const site = t('site.name')
  const titleKey = route.meta.titleKey as string | undefined
  if (titleKey) {
    document.title = `${t(titleKey)} – ${site}`
    return
  }
  if (route.name === 'login') {
    document.title = `${t('meta.login')} – ${site}`
    return
  }
  if (route.path.startsWith('/admin')) {
    document.title = `${t('meta.admin')} – ${site}`
    return
  }
  document.title = site
}
