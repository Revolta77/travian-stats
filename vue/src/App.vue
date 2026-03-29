<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { brandLogoSrc } from './lib/brandLogo'
import { applyDocumentTitle } from './lib/documentTitle'
import type { AppLocale } from './lib/uiStorage'
import { useAuthStore } from './stores/auth'
import { useUiStore } from './stores/ui'

const { t, locale: i18nLocale } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const ui = useUiStore()

const FLAGS: Record<AppLocale, string> = {
  en: '🇬🇧',
  de: '🇩🇪',
  sk: '🇸🇰',
}

const languageModel = computed({
  get: (): AppLocale => ui.locale,
  set: (v: AppLocale) => {
    ui.setLocale(v)
    i18nLocale.value = v
  },
})

const languageSelectOptions = computed(() => [
  { value: 'en' as const, label: `${FLAGS.en} ${t('lang.en')}` },
  { value: 'de' as const, label: `${FLAGS.de} ${t('lang.de')}` },
  { value: 'sk' as const, label: `${FLAGS.sk} ${t('lang.sk')}` },
])

const darkMode = computed({
  get: () => ui.darkMode,
  set: (v: boolean) => ui.setDarkMode(v),
})

onMounted(() => {
  auth.syncFromStorage()
})

watch(
  () => [i18nLocale.value, route.name, route.path, route.meta.titleKey] as const,
  () => {
    applyDocumentTitle(route, t)
  },
  { immediate: true },
)

watch(
  i18nLocale,
  (loc) => {
    document.documentElement.lang = loc
  },
  { immediate: true },
)

const brandLogoUrl = brandLogoSrc()
</script>

<template>
  <div class="layout">
    <header class="header">
      <RouterLink to="/" class="brand" :aria-label="t('app.ariaBrand')">
        <img :src="brandLogoUrl" :alt="t('site.name')" class="brand-logo" />
      </RouterLink>
      <div class="header-spacer" aria-hidden="true" />
      <nav class="nav">
        <RouterLink to="/" class="nav-link" :class="{ 'nav-link--active': route.path === '/' }">
          {{ t('nav.home') }}
        </RouterLink>
        <RouterLink to="/user-stats" class="nav-link" active-class="nav-link--active">
          {{ t('nav.players') }}
        </RouterLink>
        <RouterLink to="/alliance-stats" class="nav-link" active-class="nav-link--active">
          {{ t('nav.alliances') }}
        </RouterLink>
        <RouterLink to="/village-stats" class="nav-link" active-class="nav-link--active">
          {{ t('nav.villages') }}
        </RouterLink>
        <RouterLink v-if="auth.isLoggedIn" to="/admin/servers" class="nav-link" active-class="nav-link--active">
          {{ t('nav.admin') }}
        </RouterLink>
      </nav>
      <div class="header-end">
        <div class="lang-row">
          <Select
            v-model="languageModel"
            :options="languageSelectOptions"
            option-label="label"
            option-value="value"
            class="lang-select"
            :aria-label="t('app.ariaLanguage')"
          />
        </div>
        <div class="theme-row">
          <label class="theme-label" for="theme-toggle">{{ t('app.darkMode') }}</label>
          <ToggleSwitch id="theme-toggle" v-model="darkMode" :aria-label="t('app.ariaDarkMode')" />
        </div>
      </div>
    </header>
    <main class="main">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem 1.25rem;
  padding: 0.85rem 1.5rem;
  border-bottom: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
}
.header-spacer {
  flex: 1 1 auto;
  min-width: 0;
}
.header-end {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem 1.25rem;
  flex-shrink: 0;
}
.lang-row {
  display: flex;
  align-items: center;
}
.lang-select {
  width: 10.5rem;
  min-width: 0;
}
.theme-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}
.theme-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
  cursor: pointer;
  user-select: none;
}
.brand {
  display: inline-flex;
  align-items: center;
  line-height: 0;
  text-decoration: none;
}
.brand-logo {
  display: block;
  height: 2.25rem;
  width: auto;
  max-width: min(200px, 42vw);
  object-fit: contain;
}
.nav {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1.25rem;
  margin-right: 2rem;
}
.nav-link {
  color: var(--p-text-color);
  text-decoration: none;
  font-size: 0.95rem;
}
.nav-link:hover {
  color: var(--p-primary-color);
}
.nav-link--active {
  font-weight: 600;
  color: var(--p-primary-color);
}
.main {
  flex: 1;
  padding: 1.5rem;
  max-width: 1400px;
  width: 100%;
  margin: 0 auto;
  box-sizing: border-box;
}
</style>
