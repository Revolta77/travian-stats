<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputSwitch from 'primevue/inputswitch'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { api, getAdminToken, getApiRoot, type AdminServer } from '../../lib/api'

const servers = ref<AdminServer[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)

const dialogVisible = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const editingId = ref<number | null>(null)

const form = ref({
  name: '',
  slug: '',
  base_url: '',
  timezone: 'Europe/Bratislava',
  is_active: true,
})

function openCreate() {
  editingId.value = null
  form.value = {
    name: '',
    slug: '',
    base_url: '',
    timezone: 'Europe/Bratislava',
    is_active: true,
  }
  formError.value = null
  dialogVisible.value = true
}

function openEdit(row: AdminServer) {
  editingId.value = row.id
  form.value = {
    name: row.name,
    slug: row.slug,
    base_url: row.base_url ?? '',
    timezone: row.timezone,
    is_active: row.is_active,
  }
  formError.value = null
  dialogVisible.value = true
}

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const { data } = await api.get<{ data: AdminServer[] }>('/admin/servers')
    servers.value = data.data ?? []
  } catch {
    loadError.value = 'Nepodarilo sa načítať servery.'
  } finally {
    loading.value = false
  }
}

function slugifyName() {
  if (editingId.value !== null) {
    return
  }
  if (form.value.slug.trim() !== '') {
    return
  }
  form.value.slug = form.value.name
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

async function save() {
  formError.value = null
  saving.value = true
  try {
    const payload = {
      name: form.value.name.trim(),
      slug: form.value.slug.trim(),
      base_url: form.value.base_url.trim() || null,
      timezone: form.value.timezone.trim(),
      is_active: form.value.is_active,
    }
    if (editingId.value === null) {
      await api.post('/admin/servers', payload)
    } else {
      await api.put(`/admin/servers/${editingId.value}`, payload)
    }
    dialogVisible.value = false
    await load()
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const errors = ax.response?.data?.errors
    if (errors) {
      formError.value = Object.values(errors)
        .flat()
        .join(' ')
    } else {
      formError.value = ax.response?.data?.message ?? 'Uloženie zlyhalo.'
    }
  } finally {
    saving.value = false
  }
}

const importDialogVisible = ref(false)
const importDialogTitle = ref('Import map.sql')
const importSource = ref<'table' | 'file'>('table')
const importServer = ref<AdminServer | null>(null)
const importRunning = ref(false)
const importPhase = ref<
  | 'idle'
  | 'upload'
  | 'download'
  | 'import'
  | 'aggregate'
  | 'x_world_sql'
  | 'done'
  | 'error'
>('idle')
const importStatusText = ref('')
const importTotal = ref(0)
const importCurrent = ref(0)
const importSaved = ref(0)
const importSkipped = ref(0)
const importDoneSummary = ref<string | null>(null)
const importError = ref<string | null>(null)
const importWarnings = ref<string[]>([])
let importAbort: AbortController | null = null

const fileImportDialogVisible = ref(false)
const fileImportServerId = ref<number | null>(null)
const fileImportDate = ref<Date>(new Date())
const fileImportSql = ref('')
const fileImportLocalError = ref<string | null>(null)
const pickedSqlFile = ref<File | null>(null)
const sqlFileInputRef = ref<HTMLInputElement | null>(null)

const serverSelectOptions = computed(() =>
  servers.value.map((s) => ({ label: s.name, value: s.id })),
)

function toYmd(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const importProgressPercent = computed(() => {
  const t = importTotal.value
  if (t <= 0) {
    return 0
  }
  return Math.min(100, Math.round((importCurrent.value / t) * 100))
})

const importIndeterminate = computed(
  () =>
    importRunning.value &&
    (importPhase.value === 'upload' ||
      importPhase.value === 'download' ||
      importPhase.value === 'aggregate' ||
      importPhase.value === 'x_world_sql' ||
      (importPhase.value === 'import' && importTotal.value === 0)),
)

function resetImportProgressState() {
  importPhase.value = 'idle'
  importStatusText.value = ''
  importTotal.value = 0
  importCurrent.value = 0
  importSaved.value = 0
  importSkipped.value = 0
  importDoneSummary.value = null
  importError.value = null
  importWarnings.value = []
}

function openFileImportDialog() {
  fileImportLocalError.value = null
  fileImportServerId.value = servers.value[0]?.id ?? null
  fileImportDate.value = new Date()
  fileImportSql.value = ''
  pickedSqlFile.value = null
  if (sqlFileInputRef.value) {
    sqlFileInputRef.value.value = ''
  }
  fileImportDialogVisible.value = true
}

function onSqlFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  pickedSqlFile.value = input.files?.[0] ?? null
}

async function submitFileImport() {
  fileImportLocalError.value = null
  if (fileImportServerId.value == null) {
    fileImportLocalError.value = 'Vyber server.'
    return
  }
  if (!pickedSqlFile.value && !fileImportSql.value.trim()) {
    fileImportLocalError.value = 'Vlož SQL text alebo vyber súbor.'
    return
  }
  const srv = servers.value.find((s) => s.id === fileImportServerId.value)
  if (!srv) {
    fileImportLocalError.value = 'Server sa nenašiel v zozname.'
    return
  }
  const token = getAdminToken()
  if (!token) {
    fileImportLocalError.value = 'Chýba prihlásenie.'
    return
  }

  fileImportDialogVisible.value = false
  importDialogTitle.value = 'Import zo súboru'
  importSource.value = 'file'
  importServer.value = srv
  resetImportProgressState()
  importDialogVisible.value = true
  await nextTick()
  void startImportUpload(token)
}

function openImport(row: AdminServer) {
  importDialogTitle.value = 'Import map.sql'
  importSource.value = 'table'
  importServer.value = row
  resetImportProgressState()
  importDialogVisible.value = true
}

function closeImportDialog() {
  if (importRunning.value && importAbort) {
    importAbort.abort()
  }
  importDialogVisible.value = false
  importServer.value = null
}

async function consumeImportNdjsonStream(res: Response): Promise<void> {
  if (!res.ok) {
    importPhase.value = 'error'
    const text = await res.text()
    try {
      const j = JSON.parse(text) as {
        message?: string
        errors?: Record<string, string[]>
      }
      const fromErrors = j.errors
        ? Object.values(j.errors)
            .flat()
            .filter(Boolean)
            .join(' ')
        : ''
      importError.value = fromErrors || j.message || `HTTP ${res.status}`
    } catch {
      importError.value = text.trim() || `HTTP ${res.status}`
    }
    return
  }

  const reader = res.body?.getReader()
  if (!reader) {
    importPhase.value = 'error'
    importError.value = 'Stream nie je dostupný.'
    return
  }

  const dec = new TextDecoder()
  let buffer = ''

  while (true) {
    const { done, value } = await reader.read()
    if (done) {
      break
    }
    buffer += dec.decode(value, { stream: true })
    const lines = buffer.split('\n')
    buffer = lines.pop() ?? ''
    for (const line of lines) {
      const trimmed = line.trim()
      if (!trimmed) {
        continue
      }
      let payload: Record<string, unknown>
      try {
        payload = JSON.parse(trimmed) as Record<string, unknown>
      } catch {
        continue
      }
      const ev = payload.event as string | undefined
      if (ev === 'phase') {
        const phase = payload.phase as string
        const msg = (payload.message as string) ?? ''
        if (phase === 'upload') {
          importPhase.value = 'upload'
          importStatusText.value = msg || 'Ukladám SQL…'
        } else if (phase === 'download') {
          importPhase.value = 'download'
          importStatusText.value = msg || 'Sťahujem map.sql…'
        } else if (phase === 'archive') {
          importPhase.value = 'import'
          importStatusText.value = msg || 'Importujem…'
        } else if (phase === 'aggregate') {
          importPhase.value = 'aggregate'
          importStatusText.value = msg || 'Agregujem štatistiky…'
        }
      } else if (ev === 'x_world_load') {
        importPhase.value = 'x_world_sql'
        importStatusText.value =
          (payload.message as string) ||
          `x_world: ${Number(payload.rows) || 0} riadkov…`
      } else if (ev === 'start') {
        importPhase.value = 'import'
        importTotal.value = Number(payload.total) || 0
        importStatusText.value =
          importTotal.value > 0
            ? `Importujem ${importTotal.value.toLocaleString('sk-SK')} riadkov…`
            : 'Žiadne riadky na import.'
      } else if (ev === 'progress') {
        importPhase.value = 'import'
        importCurrent.value = Number(payload.current) || 0
        importTotal.value = Number(payload.total) || importTotal.value
        importSaved.value = Number(payload.saved) || 0
        importSkipped.value = Number(payload.skipped) || 0
        importStatusText.value = `Riadok ${importCurrent.value.toLocaleString('sk-SK')} / ${importTotal.value.toLocaleString('sk-SK')} · uložené ${importSaved.value.toLocaleString('sk-SK')}`
      } else if (ev === 'done') {
        importPhase.value = 'done'
        const p = Number(payload.processed) || 0
        const s = Number(payload.skipped) || 0
        importDoneSummary.value = `Hotovo: uložené ${p.toLocaleString('sk-SK')}, preskočené ${s.toLocaleString('sk-SK')}.`
        importStatusText.value = importDoneSummary.value
        const w = payload.import_warnings
        importWarnings.value = Array.isArray(w) ? w.map((x) => String(x)) : []
      } else if (ev === 'error') {
        importPhase.value = 'error'
        importError.value = (payload.message as string) || 'Import zlyhal.'
      }
    }
  }
}

async function startImportUpload(token: string) {
  const row = importServer.value
  if (!row) {
    return
  }
  importRunning.value = true
  importAbort = new AbortController()
  importPhase.value = 'upload'
  importStatusText.value = 'Nahrávam a importujem…'
  importTotal.value = 0
  importCurrent.value = 0
  importSaved.value = 0
  importSkipped.value = 0
  importDoneSummary.value = null
  importError.value = null
  importWarnings.value = []

  const fd = new FormData()
  fd.append('snapshot_date', toYmd(fileImportDate.value))
  if (pickedSqlFile.value) {
    fd.append('sql_file', pickedSqlFile.value)
  } else {
    fd.append('sql', fileImportSql.value)
  }

  const url = `${getApiRoot()}/admin/servers/${row.id}/import-upload`

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/x-ndjson',
        Authorization: `Bearer ${token}`,
      },
      body: fd,
      signal: importAbort.signal,
    })
    await consumeImportNdjsonStream(res)
  } catch (e: unknown) {
    if (e instanceof DOMException && e.name === 'AbortError') {
      importPhase.value = 'error'
      importError.value = 'Zrušené.'
    } else {
      importPhase.value = 'error'
      importError.value = e instanceof Error ? e.message : 'Sieťová chyba.'
    }
  } finally {
    importRunning.value = false
    importAbort = null
  }
}

async function startImport() {
  const row = importServer.value
  if (!row) {
    return
  }
  const token = getAdminToken()
  if (!token) {
    importError.value = 'Chýba prihlásenie.'
    return
  }

  importRunning.value = true
  importAbort = new AbortController()
  importPhase.value = 'download'
  importStatusText.value = 'Pripravujem sťahovanie…'
  importTotal.value = 0
  importCurrent.value = 0
  importSaved.value = 0
  importSkipped.value = 0
  importDoneSummary.value = null
  importError.value = null
  importWarnings.value = []

  const url = `${getApiRoot()}/admin/servers/${row.id}/import`

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/x-ndjson',
        Authorization: `Bearer ${token}`,
      },
      signal: importAbort.signal,
    })
    await consumeImportNdjsonStream(res)
  } catch (e: unknown) {
    if (e instanceof DOMException && e.name === 'AbortError') {
      importPhase.value = 'error'
      importError.value = 'Zrušené.'
    } else {
      importPhase.value = 'error'
      importError.value = e instanceof Error ? e.message : 'Sieťová chyba.'
    }
  } finally {
    importRunning.value = false
    importAbort = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <div class="head">
      <h1>Servery</h1>
      <div class="head-actions">
        <Button
          label="Import zo súboru"
          icon="pi pi-upload"
          severity="info"
          @click="openFileImportDialog"
        />
        <Button label="Pridať server" icon="pi pi-plus" @click="openCreate" />
      </div>
    </div>
    <Message v-if="loadError" severity="error" class="mb-3">{{ loadError }}</Message>
    <DataTable :value="servers" :loading="loading" data-key="id" striped-rows>
      <Column field="name" header="Názov" />
      <Column field="slug" header="Slug" />
      <Column field="base_url" header="Base URL">
        <template #body="{ data }">
          {{ data.base_url || '—' }}
        </template>
      </Column>
      <Column field="timezone" header="Časová zóna" />
      <Column field="is_active" header="Aktívny">
        <template #body="{ data }">
          {{ data.is_active ? 'áno' : 'nie' }}
        </template>
      </Column>
      <Column header="" style="width: 14rem">
        <template #body="{ data }">
          <div class="row-actions">
            <Button label="Upraviť" size="small" text @click="openEdit(data)" />
            <Button
              label="Importovať"
              size="small"
              text
              severity="secondary"
              icon="pi pi-download"
              :disabled="!data.is_active"
              :title="!data.is_active ? 'Neaktívny server — import nie je povolený.' : 'Stiahnuť map.sql a importovať'"
              @click="openImport(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <Dialog
      v-model:visible="dialogVisible"
      :header="editingId === null ? 'Nový server' : 'Upraviť server'"
      modal
      class="w-full"
      style="max-width: 32rem"
    >
      <form class="dlg-form" @submit.prevent="save">
        <Message v-if="formError" severity="error" class="mb-2" :closable="true" @close="formError = null">
          {{ formError }}
        </Message>
        <div class="field">
          <label for="n">Názov</label>
          <InputText id="n" v-model="form.name" class="w-full" fluid @blur="slugifyName" />
        </div>
        <div class="field">
          <label for="s">Slug</label>
          <InputText id="s" v-model="form.slug" class="w-full" fluid />
          <small class="hint">Len malé písmená, čísla a pomlčky (napr. s1-sk).</small>
        </div>
        <div class="field">
          <label for="u">Base URL</label>
          <InputText id="u" v-model="form.base_url" class="w-full" fluid placeholder="https://s1.travian.sk" />
        </div>
        <div class="field">
          <label for="tz">Časová zóna</label>
          <InputText id="tz" v-model="form.timezone" class="w-full" fluid />
        </div>
        <div class="field row">
          <label for="act">Aktívny</label>
          <InputSwitch id="act" v-model="form.is_active" />
        </div>
        <div class="dlg-actions">
          <Button type="button" label="Zrušiť" severity="secondary" text @click="dialogVisible = false" />
          <Button type="submit" label="Uložiť" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <Dialog
      v-model:visible="fileImportDialogVisible"
      header="Import zo súboru"
      modal
      class="w-full"
      style="max-width: 36rem"
    >
      <Message v-if="fileImportLocalError" severity="error" class="mb-2">{{ fileImportLocalError }}</Message>
      <div class="dlg-form file-import-form">
        <div class="field">
          <label for="fi-srv">Server</label>
          <Select
            id="fi-srv"
            v-model="fileImportServerId"
            :options="serverSelectOptions"
            option-label="label"
            option-value="value"
            placeholder="Vyber server"
            class="w-full"
            show-clear
          />
        </div>
        <div class="field">
          <label for="fi-date">Dátum snímky</label>
          <DatePicker
            id="fi-date"
            v-model="fileImportDate"
            date-format="dd.mm.yy"
            show-icon
            class="w-full"
          />
        </div>
        <div class="field">
          <label for="fi-file">SQL súbor (voliteľné)</label>
          <input
            id="fi-file"
            ref="sqlFileInputRef"
            type="file"
            accept=".sql,.txt,text/plain"
            class="file-input"
            @change="onSqlFileChange"
          />
        </div>
        <div class="field">
          <label for="fi-sql">Alebo vlož SQL</label>
          <Textarea
            id="fi-sql"
            v-model="fileImportSql"
            rows="10"
            class="w-full"
            auto-resize
            placeholder="Obsah map.sql…"
          />
        </div>
        <div class="dlg-actions">
          <Button
            type="button"
            label="Zrušiť"
            severity="secondary"
            text
            @click="fileImportDialogVisible = false"
          />
          <Button type="button" label="Importovať" icon="pi pi-upload" @click="submitFileImport" />
        </div>
      </div>
    </Dialog>

    <Dialog
      v-model:visible="importDialogVisible"
      :header="importDialogTitle"
      modal
      class="w-full"
      style="max-width: 80vw"
      :closable="!importRunning"
      @hide="closeImportDialog"
    >
      <div v-if="importServer" class="import-dlg">
        <p class="import-server-name">
          <strong>{{ importServer.name }}</strong>
          <span class="muted">({{ importServer.slug }})</span>
        </p>
        <Message v-if="importError" severity="error" class="mb-2">{{ importError }}</Message>
        <template v-else>
          <Message v-if="importDoneSummary" severity="success" class="mb-2">{{ importDoneSummary }}</Message>
          <Message v-for="(w, i) in importWarnings" :key="i" severity="warn" class="mb-2">{{ w }}</Message>
        </template>
        <p v-if="importStatusText" class="status-line">{{ importStatusText }}</p>
        <ProgressBar
          class="import-bar"
          :mode="importIndeterminate ? 'indeterminate' : 'determinate'"
          :value="importProgressPercent"
        />
        <div class="dlg-actions import-actions">
          <Button
            type="button"
            label="Zavrieť"
            severity="secondary"
            text
            :disabled="importRunning"
            @click="closeImportDialog"
          />
          <Button
            v-if="importSource === 'table'"
            type="button"
            label="Spustiť import"
            icon="pi pi-play"
            :loading="importRunning"
            :disabled="importRunning || importPhase === 'done'"
            @click="startImport"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}
.head-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
.file-input {
  width: 100%;
  font-size: 0.9rem;
}
.file-import-form {
  padding-top: 0.25rem;
}
.head h1 {
  margin: 0;
  font-size: 1.35rem;
}
.dlg-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding-top: 0.5rem;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.field.row {
  flex-direction: row;
  align-items: center;
  gap: 0.75rem;
}
label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
}
.hint {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
}
.dlg-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}
.row-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.15rem;
}
.import-dlg {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding-top: 0.25rem;
}
.import-server-name {
  margin: 0;
  font-size: 0.95rem;
}
.import-server-name .muted {
  color: var(--p-text-muted-color);
  font-weight: 400;
  margin-left: 0.35rem;
}
.status-line {
  margin: 0;
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}
.import-bar {
  margin-top: 0.25rem;
}
.import-actions {
  margin-top: 0.75rem;
}
</style>
