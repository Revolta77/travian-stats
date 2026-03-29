<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
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
import { useTribeLabel } from '../composables/useTribeLabel'
import { api, type ServerOption, type TribeOption, type VillageStatsResponse } from '../lib/api'
import { gameKarteUrl, trimGameBaseUrl } from '../lib/gameLinks'
import { useVillageStatsStore, type VillageStatsSortBy } from '../stores/villageStats'

const { t, locale } = useI18n()
const { tribeLabel } = useTribeLabel()
const store = useVillageStatsStore()
const route = useRoute()
const router = useRouter()

const servers = ref<ServerOption[]>([])
const serversLoading = ref(false)
const serversError = ref<string | null>(null)

const tribes = ref<TribeOption[]>([])
const tribesError = ref<string | null>(null)

const result = ref<VillageStatsResponse | null>(null)
const loading = ref(false)
const clientError = ref<string | null>(null)
const validationErrors = ref<Record<string, string[]> | null>(null)

const serverOptions = computed(() =>
  servers.value.map((s) => ({ label: s.name, value: s.id })),
)

const tribeSelectOptions = computed(() => {
  void locale.value
  return tribes.value.map((opt) => ({ label: tribeLabel(opt.id, opt.label), value: opt.id }))
})

function parseQueryPositiveInt(v: unknown): number | null {
  const raw = Array.isArray(v) ? v[0] : v
  if (raw === undefined || raw === null || raw === '') {
    return null
  }
  const n = Number.parseInt(String(raw), 10)
  return Number.isFinite(n) && n > 0 ? n : null
}

function routeHasVillageDeepLinkQuery(): boolean {
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

const villageTableFieldBySort: Record<VillageStatsSortBy, string> = {
  distance: 'distance',
  population: 'village.population',
  account: 'account.name',
  village: 'village.name',
  alliance: 'alliance.tag',
}

const villageSortField = computed(() => villageTableFieldBySort[store.sortBy])
const villageSortOrder = computed(() => (store.sortDir === 'asc' ? 1 : -1))

const serverGameBase = computed(() => {
  if (store.serverId === null) {
    return null
  }
  const s = servers.value.find((x) => x.id === store.serverId)
  return trimGameBaseUrl(s?.base_url)
})

function villageStatsQueryParams(): Record<string, string | number | boolean> {
  const p: Record<string, string | number | boolean> = {
    server_id: store.serverId!,
    page: store.page,
    sort_by: store.sortBy,
    sort_dir: store.sortDir,
  }
  if (store.contextPlayerId !== null) {
    p.player_id = store.contextPlayerId
  } else if (store.contextAllianceId !== null) {
    p.alliance_id = store.contextAllianceId
  } else if (store.hasCoordsForSort) {
    p.x = store.coordX!
    p.y = store.coordY!
  }
  const af = store.tableAccountFilter.trim()
  if (af) {
    p.account_filter = af
  }
  const vf = store.tableVillageFilter.trim()
  if (vf) {
    p.village_filter = vf
  }
  if (store.excludeMyAccount) {
    p.exclude_my_account = true
    const mine = store.myAccountName.trim()
    if (mine) {
      p.my_account_name = mine
    }
  }
  const al = store.tableAllianceFilter.trim()
  if (al) {
    p.alliance_filter = al
  }
  if (store.tableTribeId !== null) {
    p.tribe = store.tableTribeId
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
    serversError.value = t('villageStats.serversLoadError')
  } finally {
    serversLoading.value = false
  }
}

async function loadTribes() {
  tribesError.value = null
  try {
    const { data } = await api.get<{ data: TribeOption[] }>('/tribes')
    tribes.value = data.data ?? []
  } catch {
    tribesError.value = t('villageStats.tribesLoadError')
  }
}

function validateBeforeSearch(): boolean {
  clientError.value = null
  validationErrors.value = null

  if (store.serverId === null) {
    clientError.value = t('villageStats.selectServer')
    return false
  }
  const cx = store.coordX
  const cy = store.coordY
  if ((cx === null) !== (cy === null)) {
    clientError.value = t('villageStats.coordsBothOrEmpty')
    return false
  }
  if (cx !== null && cy !== null && !store.coordsOptionalValid) {
    clientError.value = t('villageStats.coordsRange', { min: -400, max: 400 })
    return false
  }
  if (store.excludeMyAccount && !store.myAccountName.trim()) {
    clientError.value = t('villageStats.enterMyAccountName')
    return false
  }
  return true
}

async function runSearch() {
  if (!validateBeforeSearch()) {
    return
  }

  store.ensureSortCompatibleWithCoords()

  loading.value = true
  validationErrors.value = null
  clientError.value = null

  try {
    const { data } = await api.get<VillageStatsResponse>('/village-stats', {
      params: villageStatsQueryParams(),
    })
    result.value = data
    store.setPage(data.meta.current_page)
  } catch (e) {
    result.value = null
    if (axios.isAxiosError(e) && e.response?.status === 422) {
      const body = e.response.data as { errors?: Record<string, string[]> }
      validationErrors.value = body.errors ?? null
    } else {
      clientError.value = t('villageStats.requestFailed')
    }
  } finally {
    loading.value = false
  }
}

function onSearchClick() {
  store.contextPlayerId = null
  store.contextAllianceId = null
  store.resetPage()
  void runSearch()
}

function onApplyTableFilters() {
  if (!validateBeforeSearch()) {
    return
  }
  store.resetPage()
  void runSearch()
}

const sortFieldToApi: Record<string, VillageStatsSortBy> = {
  distance: 'distance',
  'account.name': 'account',
  'village.population': 'population',
  'village.name': 'village',
  'alliance.tag': 'alliance',
}

function onVillageSort(event: { sortField?: unknown; sortOrder?: number | null }) {
  const f = event.sortField
  const o = event.sortOrder
  if (typeof f !== 'string' || o == null) {
    return
  }
  const apiSort = sortFieldToApi[f]
  if (apiSort === undefined) {
    return
  }
  if (!result.value?.meta.has_coordinates && apiSort === 'distance') {
    return
  }
  store.setSortBy(apiSort)
  store.setSortDir(o === 1 ? 'asc' : 'desc')
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
    if (list.length > 0 && store.serverId === null && !routeHasVillageDeepLinkQuery()) {
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
  store.resetPage()
  await router.replace({ name: 'village-stats', query: {} })
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
  void loadTribes()
})
</script>

<template>
  <div class="page">
    <h1>{{ t('meta.villages') }}</h1>
    <p class="lead">
      {{ t('villageStats.lead') }}
    </p>

    <Message v-if="serversError" severity="warn" class="mb-3" :closable="true">
      {{ serversError }}
    </Message>
    <Message v-if="tribesError" severity="warn" class="mb-3" :closable="true">
      {{ tribesError }}
    </Message>

    <div class="toolbar">
      <div class="toolbar-fields">
        <div class="field">
          <label for="srv">{{ t('villageStats.labelServer') }}</label>
          <Select
            id="srv"
            v-model="store.serverId"
            :options="serverOptions"
            option-label="label"
            option-value="value"
            :placeholder="t('villageStats.serverPlaceholder')"
            :loading="serversLoading"
            class="server-select"
            show-clear
          />
        </div>
        <div class="field">
          <label for="cx">{{ t('villageStats.labelX') }}</label>
          <InputNumber
            id="cx"
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
          <label for="cy">{{ t('villageStats.labelY') }}</label>
          <InputNumber
            id="cy"
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
        <label class="invisible">{{ t('villageStats.actionAria') }}</label>
        <Button :label="t('villageStats.showVillages')" icon="pi pi-map" @click="onSearchClick" />
      </div>
    </div>

    <div class="toolbar-extra">
      <div class="mine-opt">
        <Checkbox v-model="store.excludeMyAccount" input-id="hide-mine" binary />
        <label for="hide-mine">{{ t('villageStats.hideMyVillages') }}</label>
      </div>
      <InputText
        v-if="store.excludeMyAccount"
        v-model="store.myAccountName"
        :placeholder="t('villageStats.myAccountPlaceholder')"
        class="mine-input"
        autocomplete="username"
      />
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
            <label for="tf-acc">{{ t('villageStats.filterAccount') }}</label>
            <InputText
              id="tf-acc"
              v-model="store.tableAccountFilter"
              :placeholder="t('villageStats.placeholderAccount')"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
          <div class="field tf-field">
            <label for="tf-vil">{{ t('villageStats.filterVillage') }}</label>
            <InputText
              id="tf-vil"
              v-model="store.tableVillageFilter"
              :placeholder="t('villageStats.placeholderVillage')"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
          <div class="field tf-field">
            <label for="tf-al">{{ t('villageStats.filterAlliance') }}</label>
            <InputText
              id="tf-al"
              v-model="store.tableAllianceFilter"
              :placeholder="t('villageStats.placeholderAlliance')"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
          <div class="field tf-field tf-tribe-field">
            <label for="tf-tribe">{{ t('villageStats.labelTribe') }}</label>
            <Select
              id="tf-tribe"
              v-model="store.tableTribeId"
              :options="tribeSelectOptions"
              option-label="label"
              option-value="value"
              :placeholder="t('villageStats.tribeAll')"
              class="tf-tribe-select"
              show-clear
            />
          </div>
        </div>
        <div class="field tf-action">
          <label class="invisible">{{ t('villageStats.filtersAria') }}</label>
          <Button
            :label="t('villageStats.applyFilters')"
            icon="pi pi-filter"
            severity="contrast"
            @click="onApplyTableFilters"
          />
        </div>
      </div>

      <DataTable
        :value="result.rows"
        data-key="village_id"
        striped-rows
        sort-mode="single"
        removable-sort
        :sort-field="villageSortField"
        :sort-order="villageSortOrder"
        class="mt-3 village-stats-table"
        responsive-layout="scroll"
        @sort="onVillageSort"
      >
        <Column
          v-if="result.meta.has_coordinates"
          field="distance"
          :header="t('villageStats.colDistance')"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 7rem"
        >
          <template #body="{ data }">
            <div class="dist-cell">{{ data.distance ?? t('common.emDash') }}</div>
          </template>
        </Column>
        <Column field="account.name" :header="t('villageStats.colAccount')" sortable body-class="col-top">
          <template #body="{ data }">
            <div class="cell-stack">
              <RouterLink
                v-if="store.serverId !== null"
                class="stat-link"
                :to="{
                  name: 'user-stats',
                  query: { server_id: String(store.serverId), player_id: String(data.player_id) },
                }"
              >
                <strong>{{ data.account.name }}</strong>
              </RouterLink>
              <strong v-else>{{ data.account.name }}</strong>
              <span class="sub">{{ t('villageStats.accountMetaPop') }} <b>{{ data.account.total_population }}</b></span>
              <span class="sub">{{ t('villageStats.accountMetaVillages') }} <b>{{ data.account.village_count }}</b></span>
            </div>
          </template>
        </Column>
        <Column
          field="village.population"
          :header="t('villageStats.colPopulation')"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 7rem"
        >
          <template #body="{ data }">
            <span class="pop-val">{{ data.village.population ?? t('common.emDash') }}</span>
          </template>
        </Column>
        <Column field="village.name" :header="t('villageStats.colVillage')" sortable body-class="col-top">
          <template #body="{ data }">
            <div class="cell-stack">
              <strong>
                {{ data.village.name }} · ({{ data.village.x }}|{{ data.village.y }})
              </strong>
              <span class="sub"><b>{{ tribeLabel(data.village.tribe, data.village.tribe_label) }}</b> · {{ t('villageStats.villageMetaUnchanged') }} <b>{{ data.village.days_without_change }}</b></span>
            </div>
          </template>
        </Column>
        <Column
          field="alliance.tag"
          :header="t('villageStats.colAlliance')"
          sortable
          body-class="col-mid"
          style="width: 8rem"
        >
          <template #body="{ data }">
            <RouterLink
              v-if="store.serverId !== null && data.alliance_id != null && data.alliance?.tag"
              class="stat-link alliance-tag"
              :to="{
                name: 'alliance-stats',
                query: { server_id: String(store.serverId), alliance_id: String(data.alliance_id) },
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
          :header="t('villageStats.colAction')"
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 3.5rem"
        >
          <template #body="{ data }">
            <GameExternalEyeLink
              :href="
                serverGameBase
                  ? gameKarteUrl(serverGameBase, data.village.x, data.village.y)
                  : null
              "
              :label="t('villageStats.openInGameMap')"
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
      <p v-else class="muted mt-3">{{ t('villageStats.noVillages') }}</p>
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
.cell-stack {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.sub {
  font-size: 0.8rem;
  color: var(--p-text-muted-color, #64748b);
}
.pop-val {
  font-weight: 700;
  font-size: 1rem;
}
.muted {
  color: var(--p-text-muted-color, #64748b);
}
.toolbar-extra {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem 1.25rem;
  margin-top: 0.85rem;
}
.mine-opt {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.mine-opt label {
  margin: 0;
  cursor: pointer;
  font-weight: 500;
}
.mine-input {
  min-width: 12rem;
  max-width: 22rem;
  flex: 1 1 12rem;
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
.tf-tribe-field {
  min-width: 11rem;
  max-width: 16rem;
}
.tf-tribe-select {
  width: 100%;
}
.stat-link {
  color: var(--p-primary-color);
  text-decoration: none;
}
.stat-link:hover {
  text-decoration: underline;
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
.village-stats-table :deep(th.col-head-mid) {
  text-align: center;
}
.village-stats-table :deep(td.col-mid) {
  vertical-align: middle;
}
.village-stats-table :deep(td.col-top) {
  vertical-align: top;
}
.server-select {
  width: 100%;
  max-width: 22rem;
}
</style>
