<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
import { api, type ServerOption, type TribeOption, type VillageStatsResponse } from '../lib/api'
import { useVillageStatsStore } from '../stores/villageStats'

const store = useVillageStatsStore()

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

const tribeSelectOptions = computed(() =>
  tribes.value.map((t) => ({ label: t.label, value: t.id })),
)

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

function villageStatsQueryParams(): Record<string, string | number | boolean> {
  const p: Record<string, string | number | boolean> = {
    server_id: store.serverId!,
    x: store.coordX!,
    y: store.coordY!,
    page: store.page,
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
    serversError.value = 'Nepodarilo sa načítať zoznam serverov.'
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
    tribesError.value = 'Nepodarilo sa načítať kmene.'
  }
}

function validateBeforeSearch(): boolean {
  clientError.value = null
  validationErrors.value = null

  if (store.serverId === null) {
    clientError.value = 'Vyberte server.'
    return false
  }
  if (!store.coordsValid) {
    clientError.value = `Zadajte celočíselné súradnice X a Y v rozsahu ${-400}…${400}.`
    return false
  }
  if (store.excludeMyAccount && !store.myAccountName.trim()) {
    clientError.value = 'Zadajte názov svojho účtu.'
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
      clientError.value = 'Požiadavka zlyhala. Skúste znova.'
    }
  } finally {
    loading.value = false
  }
}

function onSearchClick() {
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

onMounted(() => {
  void loadServers()
  void loadTribes()
})
</script>

<template>
  <div class="page">
    <h1>Village stats</h1>
    <p class="lead">
      Všetky dediny servera v okolí zadaného bodu — zobrazí sa najviac 100 najbližších, zoradených podľa vzdialenosti.
      V tabuľke sú denné zmeny populácie za posledných 7 dní a počet po sebe idúcich dní bez zmeny populácie (podľa posledného importu).
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
          <label for="srv">Server</label>
          <Select
            id="srv"
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
          <label for="cx">X</label>
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
          <label for="cy">Y</label>
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
        <label class="invisible">Akcia</label>
        <Button label="Zobraziť dediny" icon="pi pi-map" @click="onSearchClick" />
      </div>
    </div>

    <div class="toolbar-extra">
      <div class="mine-opt">
        <Checkbox v-model="store.excludeMyAccount" input-id="hide-mine" binary />
        <label for="hide-mine">Nezobrazovať moje dediny</label>
      </div>
      <InputText
        v-if="store.excludeMyAccount"
        v-model="store.myAccountName"
        placeholder="Názov môjho účtu"
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
            <label for="tf-acc">Filter účtu</label>
            <InputText
              id="tf-acc"
              v-model="store.tableAccountFilter"
              placeholder="Časť mena hráča"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
          <div class="field tf-field">
            <label for="tf-vil">Filter dediny</label>
            <InputText
              id="tf-vil"
              v-model="store.tableVillageFilter"
              placeholder="Názov alebo x|y"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
          <div class="field tf-field">
            <label for="tf-al">Filter aliancie</label>
            <InputText
              id="tf-al"
              v-model="store.tableAllianceFilter"
              placeholder="Časť tagu aliancie"
              class="tf-input"
              @keydown.enter.prevent="onApplyTableFilters"
            />
          </div>
          <div class="field tf-field tf-tribe-field">
            <label for="tf-tribe">Kmeň</label>
            <Select
              id="tf-tribe"
              v-model="store.tableTribeId"
              :options="tribeSelectOptions"
              option-label="label"
              option-value="value"
              placeholder="Všetky kmene"
              class="tf-tribe-select"
              show-clear
            />
          </div>
        </div>
        <div class="field tf-action">
          <label class="invisible">Filtre</label>
          <Button label="Aplikovať filtre" icon="pi pi-filter" severity="contrast" @click="onApplyTableFilters" />
        </div>
      </div>

      <DataTable
        :value="result.rows"
        data-key="village_id"
        striped-rows
        class="mt-3 village-stats-table"
        responsive-layout="scroll"
      >
        <Column
          header="Vzdialenosť"
          header-class="col-head-mid"
          body-class="col-mid"
          style="width: 7rem"
        >
          <template #body="{ data }">
            <div class="dist-cell">{{ data.distance }}</div>
          </template>
        </Column>
        <Column header="Účet" body-class="col-top">
          <template #body="{ data }">
            <div class="cell-stack">
              <strong>{{ data.account.name }}</strong>
              <span class="sub">pop: <b>{{ data.account.total_population }}</b></span>
              <span class="sub">dedín: <b>{{ data.account.village_count }}</b></span>
            </div>
          </template>
        </Column>
        <Column header="Dedina" body-class="col-top">
          <template #body="{ data }">
            <div class="cell-stack">
              <strong>{{ data.village.name }} · ({{ data.village.x }}|{{ data.village.y }}) · {{ data.village.tribe_label }}</strong>
              <span class="sub">pop: <b>{{ data.village.population ?? '—' }}</b></span>
              <span class="sub">bez zmeny: <b>{{ data.village.days_without_change }}</b></span>
            </div>
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
      <p v-else class="muted mt-3">Na tomto serveri zatiaľ nie sú žiadne dediny.</p>
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
