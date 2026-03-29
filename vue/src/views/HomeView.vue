<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { brandLogoSrc } from '../lib/brandLogo'
import { api, type DashboardServerSummary } from '../lib/api'

const { t } = useI18n()
const logoUrl = brandLogoSrc()

const summaries = ref<DashboardServerSummary[]>([])
const loading = ref(true)
const errorText = ref<string | null>(null)

function formatIsoDate(iso: string | null): string {
  if (!iso) {
    return ''
  }
  const [y, m, d] = iso.split('-')
  if (!y || !m || !d) {
    return iso
  }
  return `${d}.${m}.${y}`
}

onMounted(async () => {
  loading.value = true
  errorText.value = null
  try {
    const { data } = await api.get<{ data: DashboardServerSummary[] }>('/dashboard/servers')
    summaries.value = data.data ?? []
  } catch {
    errorText.value = t('home.loadError')
    summaries.value = []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page">
    <h1 class="home-title">
      <img :src="logoUrl" :alt="t('site.name')" class="home-logo" />
    </h1>

    <section class="intro">
      <p class="intro-lead">
        <strong>{{ t('site.name') }}</strong>{{ t('home.introAfterBrand') }}
      </p>
      <ul class="intro-list">
        <li>
          <strong>{{ t('nav.players') }}</strong> — {{ t('home.bulletPlayers') }}
        </li>
        <li>
          <strong>{{ t('nav.alliances') }}</strong> — {{ t('home.bulletAlliances') }}
        </li>
        <li>
          <strong>{{ t('nav.villages') }}</strong> — {{ t('home.bulletVillages') }}
        </li>
      </ul>
      <p class="intro-note">
        {{ t('home.notePart1') }}
        <strong>{{ t('home.noteActiveLabel') }}</strong>
        {{ t('home.notePart2') }}
      </p>
    </section>

    <Message v-if="errorText" severity="warn" class="mb-3" :closable="true">
      {{ errorText }}
    </Message>

    <div v-if="loading" class="loading">
      <ProgressSpinner stroke-width="4" />
    </div>

    <div v-else-if="summaries.length === 0" class="empty-hint muted">
      {{ t('home.emptyServers') }}
    </div>

    <div v-else class="server-cards">
      <Card v-for="s in summaries" :key="s.server_id" class="server-card">
        <template #title>
          <span class="server-card-title">{{ s.name }}</span>
        </template>
        <template #content>
          <dl class="stats-grid">
            <div class="stat">
              <dt>{{ t('home.statAccounts') }}</dt>
              <dd>{{ s.accounts_count }}</dd>
            </div>
            <div class="stat">
              <dt>{{ t('home.statAlliances') }}</dt>
              <dd>{{ s.alliances_count }}</dd>
            </div>
            <div class="stat">
              <dt>{{ t('home.statVillages') }}</dt>
              <dd>{{ s.villages_count }}</dd>
            </div>
            <div class="stat stat--highlight">
              <dt>{{ t('home.statActivePlayers') }}</dt>
              <dd>{{ s.active_players_count }}</dd>
            </div>
          </dl>
          <p v-if="s.activity_window_end" class="window-hint muted">
            {{ t('home.windowHint', { date: formatIsoDate(s.activity_window_end) }) }}
          </p>
          <p v-else class="window-hint muted">{{ t('home.windowNoStats') }}</p>
        </template>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.page {
  max-width: 1100px;
  margin: 0 auto;
}
.home-title {
  margin: 0 0 1.25rem;
  line-height: 0;
}
.home-title img {
  margin: 0 auto;
}
.home-logo {
  display: block;
  width: auto;
  max-width: min(22rem, 88vw);
  max-height: 6.5rem;
  height: auto;
  object-fit: contain;
}
.intro {
  margin-bottom: 2rem;
}
.intro-lead {
  margin: 0 0 1rem;
  font-size: 1.05rem;
  line-height: 1.55;
  color: var(--p-text-color);
}
.intro-list {
  margin: 0 0 1rem;
  padding-left: 1.25rem;
  line-height: 1.55;
  color: var(--p-text-color);
}
.intro-list li {
  margin-bottom: 0.5rem;
}
.intro-note {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.5;
  color: var(--p-text-muted-color, #64748b);
}
.loading {
  display: flex;
  justify-content: center;
  padding: 2.5rem;
}
.empty-hint {
  padding: 1rem 0;
}
.muted {
  color: var(--p-text-muted-color, #64748b);
}
.server-cards {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
}
.server-card :deep(.p-card-title) {
  font-size: 1.15rem;
  margin-bottom: 0.75rem;
}
.server-card-title {
  font-weight: 700;
}
.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem 1rem;
  margin: 0;
}
.stat {
  margin: 0;
}
.stat dt {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--p-text-muted-color, #64748b);
  margin: 0 0 0.2rem;
}
.stat dd {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.stat--highlight dd {
  color: var(--p-primary-color);
}
.window-hint {
  margin: 1rem 0 0;
  font-size: 0.8rem;
  line-height: 1.4;
}
</style>
