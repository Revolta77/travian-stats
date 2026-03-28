<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Paginator from 'primevue/paginator'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import { api, type InactiveFinderResponse, type ServerOption } from '../lib/api'
import { useInactiveFinderStore } from '../stores/inactiveFinder'

const store = useInactiveFinderStore()

const servers = ref<ServerOption[]>([])
const serversLoading = ref(false)
const serversError = ref<string | null>(null)

const result = ref<InactiveFinderResponse | null>(null)
const loading = ref(false)
const clientError = ref<string | null>(null)
const validationErrors = ref<Record<string, string[]> | null>(null)

const serverOptions = computed(() =>
  servers.value.map((s) => ({ label: s.name, value: s.id })),
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

function changeClass(v: number | null | undefined): string {
  if (v === null || v === undefined) {
    return 'chg-empty'
  }
  if (v > 0) {
    return 'chg-pos'
  }
  if (v < 0) {
    return 'chg-neg'
  }
  return 'chg-zero'
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
  if (!store.coordsValid) {
    clientError.value = `Zadajte celočíselné súradnice X a Y v rozsahu ${-400}…${400}.`
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
    const { data } = await api.get<InactiveFinderResponse>('/inactive-finder', {
      params: {
        server_id: store.serverId,
        x: store.coordX,
        y: store.coordY,
        page: store.page,
      },
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
})
</script>

<template>
  <div class="page">
    <h1>Inactive finder</h1>
    <p class="lead">
      Dediny s aspoň jedným dňom bez zmeny populácie, zoradené podľa vzdialenosti od zadaného bodu.
    </p>

    <Message v-if="serversError" severity="warn" class="mb-3" :closable="true">
      {{ serversError }}
    </Message>

    <div class="toolbar">
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
      <div class="field field-action">
        <label class="invisible">Akcia</label>
        <Button label="Hľadať" icon="pi pi-search" @click="onSearchClick" />
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
        data-key="village_id"
        striped-rows
        class="mt-4 finder-table"
        responsive-layout="scroll"
      >
        <Column header="Vzdialenosť" style="width: 7rem">
          <template #body="{ data }">
            {{ data.distance }}
          </template>
        </Column>
        <Column header="Účet">
          <template #body="{ data }">
            <div class="cell-stack">
              <strong>{{ data.account.name }}</strong>
              <span class="sub">pop: {{ data.account.total_population }} · dedín: {{ data.account.village_count }}</span>
            </div>
          </template>
        </Column>
        <Column header="Dedina">
          <template #body="{ data }">
            <div class="cell-stack">
              <strong>{{ data.village.name }}</strong>
              <span class="sub">
                ({{ data.village.x }}|{{ data.village.y }}) · pop: {{ data.village.population ?? '—' }} ·
                {{ data.village.tribe_label }} · neaktívne dni: {{ data.village.days_without_change }}
              </span>
            </div>
          </template>
        </Column>
        <Column header="Aliancia" style="width: 8rem">
          <template #body="{ data }">
            <span class="alliance-tag">{{ data.alliance?.tag ?? '—' }}</span>
          </template>
        </Column>
        <Column
          v-for="col in result.date_columns"
          :key="col"
          :header="formatDateHeader(col)"
          style="width: 5rem"
        >
          <template #body="{ data }">
            <span :class="changeClass(data.daily_changes[col])">
              {{ formatChange(data.daily_changes[col]) }}
            </span>
          </template>
        </Column>
        <Column header="Akcie" style="width: 6rem">
          <template #body>
            <span class="muted">—</span>
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
      <p v-else class="muted mt-3">Žiadne výsledky.</p>
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
  display: grid;
  grid-template-columns: minmax(14rem, 22rem) minmax(8.5rem, 10.5rem) minmax(8.5rem, 10.5rem) auto;
  gap: 0.75rem 1.25rem;
  align-items: end;
}
@media (max-width: 720px) {
  .toolbar {
    grid-template-columns: 1fr;
  }
}
.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
}
.field-action {
  justify-self: start;
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
.chg-pos {
  color: #15803d;
  font-weight: 600;
}
.chg-neg {
  color: #b91c1c;
  font-weight: 600;
}
.chg-zero {
  color: #0f172a;
}
.chg-empty {
  color: #94a3b8;
}
.muted {
  color: var(--p-text-muted-color, #64748b);
}
.finder-table :deep(.p-datatable-tbody > tr > td) {
  vertical-align: top;
}
.server-select {
  width: 100%;
  max-width: 22rem;
}
.alliance-tag {
  font-weight: 600;
}
</style>
