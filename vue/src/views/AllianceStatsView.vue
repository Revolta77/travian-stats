<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import Paginator from 'primevue/paginator'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import GameExternalEyeLink from '../components/GameExternalEyeLink.vue'
import { api, type AllianceStatsResponse, type ServerOption } from '../lib/api'
import { gameAllianceUrl, trimGameBaseUrl } from '../lib/gameLinks'
import { useAllianceStatsStore } from '../stores/allianceStats'

const { t } = useI18n()
const store = useAllianceStatsStore()
const route = useRoute()
const router = useRouter()

const pendingAllianceIdFromRoute = ref<number | null>(null)

const servers = ref<ServerOption[]>([])
const serversLoading = ref(false)
const serversError = ref<string | null>(null)

const result = ref<AllianceStatsResponse | null>(null)
const loading = ref(false)
const clientError = ref<string | null>(null)
const validationErrors = ref<Record<string, string[]> | null>(null)

const serverOptions = computed(() => servers.value.map((s) => ({ label: s.name, value: s.id })))

const serverGameBase = computed(() => {
  if (store.serverId === null) {
    return null
  }
  const s = servers.value.find((x) => x.id === store.serverId)
  return trimGameBaseUrl(s?.base_url)
})

function parseQueryPositiveInt(v: unknown): number | null {
  const raw = Array.isArray(v) ? v[0] : v
  if (raw === undefined || raw === null || raw === '') {
    return null
  }
  const n = Number.parseInt(String(raw), 10)
  return Number.isFinite(n) && n > 0 ? n : null
}

function routeHasAllianceDeepLinkQuery(): boolean {
  return (
    parseQueryPositiveInt(route.query.server_id) !== null &&
    parseQueryPositiveInt(route.query.alliance_id) !== null
  )
}

const errorText = computed(() => {
  if (clientError.value) {
    return clientError.value
  }
  if (validationErrors.value) {
    return Object.values(validationErrors.value)
      .flat()
      .filter(Boolean)
      .join(' ')
  }
  return ''
})

function formatDateHeader(iso: string): string {
  const [, m, d] = iso.split('-')
  return `${d}.${m}.`
}

function formatChange(v: number | null | undefined): string {
  if (v === null || v === undefined) {
    return t('common.emDash')
  }
  if (v > 0) {
    return `+${v}`
  }
  return String(v)
}

function changeSeverity(
  v: number | null | undefined,
): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
  if (v === null || v === undefined) {
    return 'secondary'
  }
  if (v > 0) {
    return 'success'
  }
  if (v < 0) {
    return 'danger'
  }
  return 'info'
}

function allianceStatsQueryParams(): Record<string, string | number> {
  const p: Record<string, string | number> = {
    server_id: store.serverId!,
    page: store.page,
  }
  if (pendingAllianceIdFromRoute.value !== null) {
    p.alliance_id = pendingAllianceIdFromRoute.value
  } else {
    const tf = store.tagFilter.trim()
    if (tf) {
      p.tag_filter = tf
    }
  }
  return p
}

async function loadServers() {
  serversLoading.value = true
  serversError.value = null
  try {
    const { data } = await api.get<{ data: ServerOption[] }>('/servers')
    servers.value = data.data ?? []
  } catch {
    serversError.value = t('allianceStats.serversLoadError')
  } finally {
    serversLoading.value = false
  }
}

function validateBeforeSearch(): boolean {
  clientError.value = null
  validationErrors.value = null

  if (store.serverId === null) {
    clientError.value = t('allianceStats.selectServer')
    return false
  }

  return true
}

async function runSearch() {
  if (!validateBeforeSearch()) {
    return
  }

  loading.value = true
  validationErrors.value = null
  clientError.value = null

  try {
    const { data } = await api.get<AllianceStatsResponse>('/alliance-stats', {
      params: allianceStatsQueryParams(),
    })
    result.value = data
    store.setPage(data.meta.current_page)
    pendingAllianceIdFromRoute.value = null
  } catch (e) {
    result.value = null
    pendingAllianceIdFromRoute.value = null
    if (axios.isAxiosError(e) && e.response?.status === 422) {
      const body = e.response.data as { errors?: Record<string, string[]> }
      validationErrors.value = body.errors ?? null
    } else {
      clientError.value = t('allianceStats.requestFailed')
    }
  } finally {
    loading.value = false
  }
}

function onSearchClick() {
  store.resetPage()
  void runSearch()
}

function onPaginatorChange(event: { page: number }) {
  store.setPage(event.page + 1)
  void runSearch()
}

const paginatorFirst = computed(() => {
  if (!result.value) {
    return 0
  }
  return (result.value.meta.current_page - 1) * result.value.meta.per_page
})

watch(
  servers,
  (list) => {
    if (list.length > 0 && store.serverId === null && !routeHasAllianceDeepLinkQuery()) {
      store.serverId = list[0].id
    }
  },
  { immediate: true },
)

async function applyDeepLinkFromRoute(): Promise<void> {
  const sid = parseQueryPositiveInt(route.query.server_id)
  const aid = parseQueryPositiveInt(route.query.alliance_id)
  if (sid === null || aid === null) {
    return
  }
  store.serverId = sid
  pendingAllianceIdFromRoute.value = aid
  store.resetPage()
  await router.replace({ name: 'alliance-stats', query: {} })
  await runSearch()
}

watch(
  () => [route.query.server_id, route.query.alliance_id],
  () => {
    void applyDeepLinkFromRoute()
  },
  { immediate: true },
)

onMounted(() => {
  void loadServers()
})
</script>

<template>
  <div class="page">
    <h1>{{ t('meta.alliances') }}</h1>
    <p class="lead">
      {{ t('allianceStats.lead') }}
    </p>

    <Message v-if="serversError" severity="warn" class="mb-3" :closable="true">
      {{ serversError }}
    </Message>

    <div class="toolbar">
      <div class="toolbar-fields">
        <div class="field">
          <label for="als-srv">{{ t('allianceStats.labelServer') }}</label>
          <Select
            id="als-srv"
            v-model="store.serverId"
            :options="serverOptions"
            option-label="label"
            option-value="value"
            :placeholder="t('allianceStats.serverPlaceholder')"
            :loading="serversLoading"
            class="server-select"
            show-clear
          />
        </div>
        <div class="field field-tag">
          <label for="als-tag">{{ t('allianceStats.labelTagFilter') }}</label>
          <InputText
            id="als-tag"
            v-model="store.tagFilter"
            :placeholder="t('allianceStats.placeholderTag')"
            class="tag-input"
            @keydown.enter.prevent="onSearchClick"
          />
        </div>
      </div>
      <div class="field field-action">
        <label class="invisible">{{ t('allianceStats.actionAria') }}</label>
        <Button :label="t('allianceStats.showAlliances')" icon="pi pi-users" @click="onSearchClick" />
      </div>
    </div>

    <Message v-if="errorText" severity="error" class="mt-3" :closable="true">
      {{ errorText }}
    </Message>

    <div v-if="loading" class="loading">
      <ProgressSpinner stroke-width="4" />
    </div>

    <template v-else-if="result">
      <DataTable
        :value="result.rows"
        data-key="alliance_id"
        striped-rows
        sort-mode="single"
        class="mt-3 alliance-stats-table"
        responsive-layout="scroll"
      >
        <Column field="tag" :header="t('allianceStats.colAlliance')" sortable header-class="col-head-mid" body-class="col-mid">
          <template #body="{ data }">
            <span class="alliance-tag">{{ data.tag }}</span>
          </template>
        </Column>
        <Column
          field="member_count"
          :header="t('allianceStats.colAccounts')"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 6rem"
        >
          <template #body="{ data }">
            <RouterLink
              v-if="store.serverId !== null"
              class="stat-link num-val"
              :to="{
                name: 'user-stats',
                query: {
                  server_id: String(store.serverId),
                  alliance_id: String(data.alliance_id),
                },
              }"
            >
              {{ data.member_count }}
            </RouterLink>
            <span v-else class="num-val">{{ data.member_count }}</span>
          </template>
        </Column>
        <Column
          field="village_count"
          :header="t('allianceStats.colVillages')"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 6rem"
        >
          <template #body="{ data }">
            <RouterLink
              v-if="store.serverId !== null"
              class="stat-link num-val"
              :to="{
                name: 'village-stats',
                query: {
                  server_id: String(store.serverId),
                  alliance_id: String(data.alliance_id),
                },
              }"
            >
              {{ data.village_count }}
            </RouterLink>
            <span v-else class="num-val">{{ data.village_count }}</span>
          </template>
        </Column>
        <Column
          field="total_population"
          :header="t('allianceStats.colPopulation')"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 8rem"
        >
          <template #body="{ data }">
            <span class="pop-val">{{ data.total_population }}</span>
          </template>
        </Column>
        <Column
          v-for="col in result.date_columns"
          :key="col"
          :header="formatDateHeader(col)"
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 5.5rem"
        >
          <template #body="{ data }">
            <div class="chg-cell">
              <Tag
                :value="formatChange(data.daily_changes[col])"
                :severity="changeSeverity(data.daily_changes[col])"
                rounded
                class="chg-tag"
              />
            </div>
          </template>
        </Column>
        <Column
          :header="t('allianceStats.colAction')"
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 3.5rem"
        >
          <template #body="{ data }">
            <GameExternalEyeLink
              :href="
                serverGameBase
                  ? gameAllianceUrl(serverGameBase, data.alliance_external_id)
                  : null
              "
              :label="t('allianceStats.openInGameAlliance')"
            />
          </template>
        </Column>
      </DataTable>

      <Paginator
        v-if="result.meta.total > 0"
        :rows="result.meta.per_page"
        :total-records="result.meta.total"
        :first="paginatorFirst"
        :page-link-size="5"
        :rows-per-page-options="[]"
        template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
        class="mt-3"
        @page="onPaginatorChange"
      />
      <p v-else class="muted mt-3">{{ t('allianceStats.noAlliances') }}</p>
    </template>
  </div>
</template>

<style scoped>
.page {
  max-width: 100%;
}
.lead {
  color: var(--p-text-muted-color, #64748b);
  margin-bottom: 1.25rem;
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.75rem 1.25rem;
  width: 100%;
  margin-bottom: 1rem;
}
.toolbar-fields {
  display: flex;
  flex-wrap: wrap;
  flex: 1 1 auto;
  gap: 0.75rem 1.25rem;
  align-items: flex-end;
  min-width: 0;
}
@media (max-width: 720px) {
  .toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .field-action {
    margin-left: 0;
    align-self: flex-end;
  }
}
.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
}
.field-action {
  margin-left: auto;
  flex: 0 0 auto;
}
.field-tag {
  flex: 1 1 14rem;
  min-width: 10rem;
  max-width: 24rem;
}
label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-text-muted-color, #64748b);
}
.invisible {
  visibility: hidden;
}
.tag-input {
  width: 100%;
}
.loading {
  display: flex;
  justify-content: center;
  padding: 2rem;
}
.alliance-tag {
  font-weight: 700;
}
.num-val {
  font-weight: 600;
}
.stat-link {
  color: var(--p-primary-color);
  text-decoration: none;
}
.stat-link:hover {
  text-decoration: underline;
}
.pop-val {
  font-weight: 700;
  font-size: 1rem;
}
.muted {
  color: var(--p-text-muted-color, #64748b);
}
.chg-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
}
.chg-tag {
  font-weight: 600;
  font-size: 0.8rem;
}
.alliance-stats-table :deep(th.col-head-mid) {
  text-align: center;
}
.alliance-stats-table :deep(td.col-mid) {
  vertical-align: middle;
}
.server-select {
  width: 100%;
  max-width: 22rem;
}
</style>
