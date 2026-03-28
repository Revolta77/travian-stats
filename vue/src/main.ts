import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { applyStoredDarkModeClass } from './lib/uiStorage'
import { piniaPersistLocalStorage } from './plugins/piniaPersistLocalStorage'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import router from './router'
// @ts-ignore
import App from './App.vue'

import 'primeicons/primeicons.css'
import './style.css'

applyStoredDarkModeClass()

const app = createApp(App)

const pinia = createPinia()
pinia.use(piniaPersistLocalStorage)
app.use(pinia)
app.use(router)
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      darkModeSelector: '.dark',
    },
  },
})

app.mount('#app')
