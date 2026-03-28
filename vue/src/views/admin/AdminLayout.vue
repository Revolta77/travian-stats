<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'
import Button from 'primevue/button'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  await router.push('/login')
}
</script>

<template>
  <div class="admin-layout">
    <aside class="aside">
      <div class="aside-title">Admin</div>
      <nav class="aside-nav">
        <RouterLink to="/admin/servers" class="aside-link" active-class="aside-link--active">Servery</RouterLink>
      </nav>
      <Button label="Odhlásiť" icon="pi pi-sign-out" severity="secondary" text class="logout" @click="logout" />
    </aside>
    <div class="admin-body">
      <RouterView />
    </div>
  </div>
</template>

<style scoped>
.admin-layout {
  display: flex;
  min-height: calc(100vh - 120px);
  margin: -1.5rem;
  margin-top: 0;
}
.aside {
  width: 200px;
  flex-shrink: 0;
  border-right: 1px solid var(--p-content-border-color, #e2e8f0);
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  background: var(--p-surface-50, #f8fafc);
}
.aside-title {
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
}
.aside-nav {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.aside-link {
  padding: 0.5rem 0.65rem;
  border-radius: 6px;
  color: var(--p-text-color);
  text-decoration: none;
  font-size: 0.95rem;
}
.aside-link:hover {
  background: var(--p-surface-100, #f1f5f9);
}
.aside-link--active {
  background: var(--p-primary-50, #eff6ff);
  color: var(--p-primary-color);
  font-weight: 600;
}
.logout {
  margin-top: auto;
  align-self: flex-start;
}
.admin-body {
  flex: 1;
  padding: 1.5rem;
  max-width: 1200px;
}
</style>
