<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
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
import { api, type ServerOption, type UserStatsResponse } from '../lib/api'
import { useUserStatsStore } from '../stores/userStats'

const store = useUserStatsStore()

const servers = ref<ServerOption[]>([])
const serversLoading = ref(false)
const serversError = ref<string | null>(null)

const result = ref<UserStatsResponse | null>(null)
const loading = ref(false)
const clientError = ref<string | null>(null)
const validationErrors = ref<Record<string, string[]> | null>(null)

const serverOptions = computed(() => servers.value.map((s) => ({ label: s.name, value: s.id })))

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
    return '—'
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
  return p
}

async function loadServers() {
  serversLoading.value = true
  serversError.value = null
  try {
    const { data } = await api.get<{ data: ServerOption[] }>('/servers')
    servers.value = data.data ?? []
  } catch {
    serversError.value = 'Nepodarilo sa načítať zoznam serverov.'
  } finally {
    serversLoading.value = false
  }
}

function validateBeforeSearch(): boolean {
  clientError.value = null
  validationErrors.value = null

  if (store.serverId === null) {
    clientError.value = 'Vyberte server.'
    return false
  }

  const cx = store.coordX
  const cy = store.coordY
  if ((cx === null) !== (cy === null)) {
    clientError.value = 'Zadajte obe súradnice X a Y, alebo ich nechajte prázdne.'
    return false
  }
  if (cx !== null && cy !== null && !store.coordsOptionalValid) {
    clientError.value = `Súradnice musia byť celé čísla v rozsahu ${-400}…${400}.`
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
      clientError.value = 'Požiadavka zlyhala. Skúste znova.'
    }
  } finally {
    loading.value = false
  }
}

function onSearchClick() {
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
    if (list.length > 0 && store.serverId === null) {
      store.serverId = list[0].id
    }
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
    <h1>User stats</h1>
    <p class="lead">
      Prehľad hráčov na serveri podľa celkovej populácie (posledný import) alebo podľa vzdialenosti od bodu X|Y, ak súradnice
      vyplníte. Zmeny populácie sú za účet ako súčet z importovaných denných štatistík hráča.
    </p>

    <Message v-if="serversError" severity="warn" class="mb-3" :closable="true">
      {{ serversError }}
    </Message>
    <div class="toolbar">
      <div class="toolbar-fields">
        <div class="field">
          <label for="usr-srv">Server</label>
          <Select
            id="usr-srv"
            v-model="store.serverId"
            :options="serverOptions"
            option-label="label"
            option-value="value"
            placeholder="Vyberte server"
            :loading="serversLoading"
            class="server-select"
            show-clear
          />
        </div>
        <div class="field">
          <label for="usr-cx">X</label>
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
          <label for="usr-cy">Y</label>
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
        <label class="invisible">Akcia</label>
        <Button label="Zobraziť hráčov" icon="pi pi-users" @click="onSearchClick" />
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
            <label for="usr-tf-acc">Filter účtu</label>
            <InputText
              id="usr-tf-acc"
              v-model="store.tableAccountFilter"
              placeholder="Časť mena hráča"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
          <div class="field tf-field">
            <label for="usr-tf-al">Filter aliancie</label>
            <InputText
              id="usr-tf-al"
              v-model="store.tableAllianceFilter"
              placeholder="Časť tagu aliancie"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
        </div>
        <div class="field tf-action">
          <label class="invisible">Filtre</label>
          <Button label="Aplikovať filtre" icon="pi pi-filter" severity="secondary" @click="onApplyTableFilters" />
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
          header="Vzdialenosť"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 7rem"
        >
          <template #body="{ data }">
            <div class="dist-cell">{{ data.distance ?? '—' }}</div>
          </template>
        </Column>
        <Column field="account.name" header="Účet" sortable body-class="col-top">
          <template #body="{ data }">
            <strong>{{ data.account.name }}</strong>
          </template>
        </Column>
        <Column
          field="village_count"
          header="Dedín"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 6rem"
        >
          <template #body="{ data }">
            <span class="village-count-val">{{ data.village_count }}</span>
          </template>
        </Column>
        <Column
          field="account.total_population"
          header="Populácia"
          sortable
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 8rem"
        >
          <template #body="{ data }">
            <span class="pop-val">{{ data.account.total_population }}</span>
          </template>
        </Column>
        <Column header="Aliancia" body-class="col-mid" style="width: 8rem">
          <template #body="{ data }">
            <span class="alliance-tag">{{ data.alliance?.tag ?? '—' }}</span>
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
      <p v-else class="muted mt-3">Žiadni hráči podľa zadaných kritérií.</p>
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
.village-count-val {
  font-weight: 600;
  font-size: 1rem;
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
