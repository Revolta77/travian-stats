<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import Paginator from 'primevue/paginator'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import GameExternalEyeLink from '../components/GameExternalEyeLink.vue'
import { api, type ServerOption, type UserStatsResponse } from '../lib/api'
import { gameProfileUrl, trimGameBaseUrl } from '../lib/gameLinks'
import { useUserStatsStore } from '../stores/userStats'

const { t } = useI18n()
const store = useUserStatsStore()
const route = useRoute()
const router = useRouter()

const servers = ref<ServerOption[]>([])
const serversLoading = ref(false)
const serversError = ref<string | null>(null)

const result = ref<UserStatsResponse | null>(null)
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

function routeHasUserDeepLinkQuery(): boolean {
  const sid = parseQueryPositiveInt(route.query.server_id)
  if (sid === null) {
    return false
  }
  return (
    parseQueryPositiveInt(route.query.player_id) !== null ||
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

function userStatsQueryParams(): Record<string, string | number | boolean> {
  const p: Record<string, string | number | boolean> = {
    server_id: store.serverId!,
    page: store.page,
    sort_by: store.sortBy,
    sort_dir: store.sortDir,
  }
  if (store.hasCoordsForSort) {
    p.x = store.coordX!
    p.y = store.coordY!
  }
  const af = store.tableAccountFilter.trim()
  if (af) {
    p.account_filter = af
  }
  const al = store.tableAllianceFilter.trim()
  if (al) {
    p.alliance_filter = al
  }
  if (store.contextPlayerId !== null) {
    p.player_id = store.contextPlayerId
  }
  if (store.contextAllianceId !== null) {
    p.alliance_id = store.contextAllianceId
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
    serversError.value = t('userStats.serversLoadError')
  } finally {
    serversLoading.value = false
  }
}

function validateBeforeSearch(): boolean {
  clientError.value = null
  validationErrors.value = null

  if (store.serverId === null) {
    clientError.value = t('userStats.selectServer')
    return false
  }

  const cx = store.coordX
  const cy = store.coordY
  if ((cx === null) !== (cy === null)) {
    clientError.value = t('userStats.coordsBothOrEmpty')
    return false
  }
  if (cx !== null && cy !== null && !store.coordsOptionalValid) {
    clientError.value = t('userStats.coordsRange', { min: -400, max: 400 })
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
    const { data } = await api.get<UserStatsResponse>('/user-stats', {
      params: userStatsQueryParams(),
    })
    result.value = data
    store.setPage(data.meta.current_page)
  } catch (e) {
    result.value = null
    if (axios.isAxiosError(e) && e.response?.status === 422) {
      const body = e.response.data as { errors?: Record<string, string[]> }
      validationErrors.value = body.errors ?? null
    } else {
      clientError.value = t('userStats.requestFailed')
    }
  } finally {
    loading.value = false
  }
}

function onSearchClick() {
  store.contextPlayerId = null
  store.contextAllianceId = null
  store.ensureSortCompatibleWithCoords()
  store.resetPage()
  void runSearch()
}

function onApplyTableFilters() {
  if (!validateBeforeSearch()) {
    return
  }
  store.ensureSortCompatibleWithCoords()
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
  () => [store.coordX, store.coordY] as const,
  () => {
    store.ensureSortCompatibleWithCoords()
  },
)

watch(
  servers,
  (list) => {
    if (list.length > 0 && store.serverId === null && !routeHasUserDeepLinkQuery()) {
      store.serverId = list[0].id
    }
  },
  { immediate: true },
)

async function applyDeepLinkFromRoute(): Promise<void> {
  const sid = parseQueryPositiveInt(route.query.server_id)
  const pid = parseQueryPositiveInt(route.query.player_id)
  const aid = parseQueryPositiveInt(route.query.alliance_id)
  if (sid === null) {
    return
  }
  if (pid !== null) {
    store.serverId = sid
    store.contextPlayerId = pid
    store.contextAllianceId = null
  } else if (aid !== null) {
    store.serverId = sid
    store.contextAllianceId = aid
    store.contextPlayerId = null
  } else {
    return
  }
  store.ensureSortCompatibleWithCoords()
  store.resetPage()
  await router.replace({ name: 'user-stats', query: {} })
  await runSearch()
}

watch(
  () => [route.query.server_id, route.query.player_id, route.query.alliance_id],
  () => {
    void applyDeepLinkFromRoute()
  },
  { immediate: true },
)

onMounted(() => {
  store.ensureSortCompatibleWithCoords()
  void loadServers()
})
</script>

<template>
  <div class="page">
    <h1>{{ t('meta.players') }}</h1>
    <p class="lead">
      {{ t('userStats.lead') }}
    </p>

    <Message v-if="serversError" severity="warn" class="mb-3" :closable="true">
      {{ serversError }}
    </Message>
    <div class="toolbar">
      <div class="toolbar-fields">
        <div class="field">
          <label for="usr-srv">{{ t('userStats.labelServer') }}</label>
          <Select
            id="usr-srv"
            v-model="store.serverId"
            :options="serverOptions"
            option-label="label"
            option-value="value"
            :placeholder="t('userStats.serverPlaceholder')"
            :loading="serversLoading"
            class="server-select"
            show-clear
          />
        </div>
        <div class="field">
          <label for="usr-cx">{{ t('userStats.labelX') }}</label>
          <InputNumber
            id="usr-cx"
            v-model="store.coordX"
            :min="-400"
            :max="400"
            :step="1"
            :use-grouping="false"
            class="coord-input"
            input-class="w-full"
          />
        </div>
        <div class="field">
          <label for="usr-cy">{{ t('userStats.labelY') }}</label>
          <InputNumber
            id="usr-cy"
            v-model="store.coordY"
            :min="-400"
            :max="400"
            :step="1"
            :use-grouping="false"
            class="coord-input"
            input-class="w-full"
          />
        </div>
      </div>
      <div class="field field-action">
        <label class="invisible">{{ t('userStats.actionAria') }}</label>
        <Button :label="t('userStats.showPlayers')" icon="pi pi-users" @click="onSearchClick" />
      </div>
    </div>

    <Message v-if="errorText" severity="error" class="mt-3" :closable="true">
      {{ errorText }}
    </Message>

    <div v-if="loading" class="loading">
      <ProgressSpinner stroke-width="4" />
    </div>

    <template v-else-if="result">
      <div class="table-filters">
        <div class="table-filters-fields">
          <div class="field tf-field">
            <label for="usr-tf-acc">{{ t('userStats.filterAccount') }}</label>
            <InputText
              id="usr-tf-acc"
              v-model="store.tableAccountFilter"
              :placeholder="t('userStats.placeholderAccount')"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
          <div class="field tf-field">
            <label for="usr-tf-al">{{ t('userStats.filterAlliance') }}</label>
            <InputText
              id="usr-tf-al"
              v-model="store.tableAllianceFilter"
              :placeholder="t('userStats.placeholderAlliance')"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
        </div>
        <div class="field tf-action">
          <label class="invisible">{{ t('userStats.filtersAria') }}</label>
          <Button :label="t('userStats.applyFilters')" icon="pi pi-filter" severity="secondary" @click="onApplyTableFilters" />
        </div>
      </div>

      <DataTable
        :value="result.rows"
        data-key="player_id"
        striped-rows
        sort-mode="single"
        class="mt-3 user-stats-table"
        responsive-layout="scroll"
      >
        <Column
          v-if="result.meta.has_coordinates"
          field="distance"
          :header="t('userStats.colDistance')"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 7rem"
        >
          <template #body="{ data }">
            <div class="dist-cell">{{ data.distance ?? t('common.emDash') }}</div>
          </template>
        </Column>
        <Column field="account.name" :header="t('userStats.colAccount')" sortable body-class="col-mid">
          <template #body="{ data }">
            <strong>{{ data.account.name }}</strong>
          </template>
        </Column>
        <Column
          field="village_count"
          :header="t('userStats.colVillages')"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 6rem"
        >
          <template #body="{ data }">
            <RouterLink
              v-if="store.serverId !== null"
              class="stat-link village-count-val"
              :to="{
                name: 'village-stats',
                query: { server_id: String(store.serverId), player_id: String(data.player_id) },
              }"
            >
              {{ data.village_count }}
            </RouterLink>
            <span v-else class="village-count-val">{{ data.village_count }}</span>
          </template>
        </Column>
        <Column
          field="account.total_population"
          :header="t('userStats.colPopulation')"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 8rem"
        >
          <template #body="{ data }">
            <span class="pop-val">{{ data.account.total_population }}</span>
          </template>
        </Column>
        <Column :header="t('userStats.colAlliance')" body-class="col-mid" style="width: 8rem">
          <template #body="{ data }">
            <RouterLink
              v-if="
                store.serverId !== null &&
                data.alliance?.alliance_id != null &&
                data.alliance?.tag
              "
              class="stat-link alliance-tag"
              :to="{
                name: 'alliance-stats',
                query: {
                  server_id: String(store.serverId),
                  alliance_id: String(data.alliance.alliance_id),
                },
              }"
            >
              {{ data.alliance.tag }}
            </RouterLink>
            <span v-else class="alliance-tag">{{ data.alliance?.tag ?? t('common.emDash') }}</span>
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
          :header="t('userStats.colAction')"
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 3.5rem"
        >
          <template #body="{ data }">
            <GameExternalEyeLink
              :href="
                serverGameBase
                  ? gameProfileUrl(serverGameBase, data.player_external_id)
                  : null
              "
              :label="t('userStats.openInGameProfile')"
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
      <p v-else class="muted mt-3">{{ t('userStats.noPlayers') }}</p>
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
label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-text-muted-color, #64748b);
}
.invisible {
  visibility: hidden;
}
.coord-input {
  width: 100%;
  max-width: 100%;
}
.coord-input :deep(.p-inputnumber) {
  width: 100%;
}
.coord-input :deep(.p-inputnumber-input),
.coord-input :deep(input.p-inputtext) {
  min-width: 0;
  width: 100%;
  box-sizing: border-box;
}
.loading {
  display: flex;
  justify-content: center;
  padding: 2rem;
}
.stat-link.village-count-val {
  font-weight: 600;
  font-size: 1rem;
}
.village-count-val {
  font-weight: 600;
  font-size: 1rem;
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
.table-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.75rem 1.25rem;
  margin: 1rem 0;
  width: 100%;
}
.table-filters-fields {
  display: flex;
  flex-wrap: wrap;
  flex: 1 1 auto;
  gap: 0.75rem 1.25rem;
  align-items: flex-end;
  min-width: 0;
}
.tf-field {
  flex: 1 1 12rem;
  min-width: 10rem;
  max-width: 20rem;
}
.tf-input {
  width: 100%;
}
.tf-action {
  margin-left: auto;
  flex: 0 0 auto;
}
@media (max-width: 720px) {
  .table-filters {
    flex-direction: column;
    align-items: stretch;
  }
  .tf-action {
    margin-left: 0;
    align-self: flex-end;
  }
}
.alliance-tag {
  font-weight: 600;
}
.dist-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  font-weight: 600;
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
.user-stats-table :deep(th.col-head-mid) {
  text-align: center;
}
.user-stats-table :deep(td.col-mid) {
  vertical-align: middle;
}
.user-stats-table :deep(td.col-top) {
  vertical-align: top;
}
.server-select {
  width: 100%;
  max-width: 22rem;
}
</style>
