<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const loading = ref(false)
const errorMsg = ref<string | null>(null)

onMounted(() => {
  auth.syncFromStorage()
  if (auth.isLoggedIn) {
    void router.push((route.query.redirect as string) || '/admin/servers')
  }
})

async function submit() {
  errorMsg.value = null
  loading.value = true
  try {
    await auth.login(email.value.trim(), typeof password.value === 'string' ? password.value : '')
    await router.push((route.query.redirect as string) || '/admin/servers')
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const err = ax.response?.data?.errors
    if (err?.email?.[0]) {
      errorMsg.value = err.email[0]
    } else if (ax.response?.data?.message) {
      errorMsg.value = ax.response.data.message
    } else {
      errorMsg.value = 'Prihlásenie zlyhalo.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <Card class="login-card">
      <template #title>Admin prihlásenie</template>
      <template #content>
        <form class="form" @submit.prevent="submit">
          <Message v-if="errorMsg" severity="error" class="mb-3" :closable="true" @close="errorMsg = null">
            {{ errorMsg }}
          </Message>
          <div class="field">
            <label for="em">E-mail</label>
            <InputText id="em" v-model="email" type="email" autocomplete="username" class="w-full" fluid />
          </div>
          <div class="field">
            <label for="pw">Heslo</label>
            <Password
              id="pw"
              v-model="password"
              :feedback="false"
              toggle-mask
              input-class="w-full"
              class="w-full"
              fluid
              autocomplete="current-password"
            />
          </div>
          <Button type="submit" label="Prihlásiť sa" icon="pi pi-sign-in" :loading="loading" class="mt-2" />
        </form>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.login-page {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding-top: 3rem;
}
.login-card {
  width: 100%;
  max-width: 420px;
}
.form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-text-muted-color, #64748b);
}
</style>
