import { createRouter, createWebHistory } from 'vue-router'
import { ADMIN_TOKEN_KEY } from '../lib/api'
import HomeView from '../views/HomeView.vue'
import VillageStatsView from '../views/VillageStatsView.vue'
import AllianceStatsView from '../views/AllianceStatsView.vue'
import UserStatsView from '../views/UserStatsView.vue'
import LoginView from '../views/LoginView.vue'
import AdminLayout from '../views/admin/AdminLayout.vue'
import AdminServersView from '../views/admin/AdminServersView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/village-stats', name: 'village-stats', component: VillageStatsView },
    { path: '/inactive-finder', redirect: '/village-stats' },
    {
      path: '/alliance-stats',
      name: 'alliance-stats',
      component: AllianceStatsView,
      meta: { title: 'Alliance stats' },
    },
    {
      path: '/user-stats',
      name: 'user-stats',
      component: UserStatsView,
      meta: { title: 'User stats' },
    },
    { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { requiresAdmin: true },
      children: [
        { path: '', redirect: '/admin/servers' },
        { path: 'servers', name: 'admin-servers', component: AdminServersView },
      ],
    },
  ],
})

router.beforeEach((to) => {
  const token = localStorage.getItem(ADMIN_TOKEN_KEY)
  if (to.meta.requiresAdmin && !token) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guestOnly && token) {
    return { name: 'admin-servers' }
  }
  return true
})

export default router
