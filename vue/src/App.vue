<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import ToggleSwitch from 'primevue/toggleswitch'
import { brandLogoSrc } from './lib/brandLogo'
import { useAuthStore } from './stores/auth'
import { useUiStore } from './stores/ui'

const route = useRoute()
const auth = useAuthStore()
const ui = useUiStore()

const darkMode = computed({
  get: () => ui.darkMode,
  set: (v: boolean) => ui.setDarkMode(v),
})

onMounted(() => {
  auth.syncFromStorage()
})

const brandLogoUrl = brandLogoSrc()
</script>

<template>
  <div class="layout">
    <header class="header">
      <RouterLink to="/" class="brand" aria-label="Travian Stats – úvod">
        <img :src="brandLogoUrl" alt="Travian Stats" class="brand-logo" />
      </RouterLink>
      <div class="header-spacer" aria-hidden="true" />
      <nav class="nav">
        <RouterLink to="/" class="nav-link" :class="{ 'nav-link--active': route.path === '/' }">Úvod</RouterLink>
        <RouterLink to="/village-stats" class="nav-link" active-class="nav-link--active">Village stats</RouterLink>
        <RouterLink to="/alliance-stats" class="nav-link" active-class="nav-link--active">Alliance stats</RouterLink>
        <RouterLink to="/user-stats" class="nav-link" active-class="nav-link--active">User stats</RouterLink>
        <RouterLink v-if="auth.isLoggedIn" to="/admin/servers" class="nav-link" active-class="nav-link--active">
          Admin
        </RouterLink>
      </nav>
      <div class="theme-row">
        <label class="theme-label" for="theme-toggle">Tmavý motív</label>
        <ToggleSwitch id="theme-toggle" v-model="darkMode" aria-label="Prepínač tmavého motívu" />
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
