<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
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

const { t, te, locale } = useI18n()

function formatImportInt(n: number): string {
  const l = locale.value
  const tag = l === 'de' ? 'de-DE' : l === 'sk' ? 'sk-SK' : 'en-US'
  return n.toLocaleString(tag)
}

type ImportWarningEntry = string | { key: string }

function translateImportStreamMessage(payload: Record<string, unknown>): string {
  const mk = payload.message_key
  if (typeof mk === 'string' && mk.length > 0) {
    const i18nKey = `importStream.${mk}`
    const paramsRaw = payload.message_params
    const interp: Record<string, string> = {}
    if (paramsRaw && typeof paramsRaw === 'object' && !Array.isArray(paramsRaw)) {
      for (const [k, val] of Object.entries(paramsRaw as Record<string, unknown>)) {
        if (val == null) {
          continue
        }
        if (k === 'rows' && typeof val === 'number') {
          interp.rows = formatImportInt(val)
        } else {
          interp[k] = String(val)
        }
      }
    }
    if (payload.detail != null) {
      interp.detail = String(payload.detail)
    }
    if (te(i18nKey)) {
      return t(i18nKey, interp)
    }
  }
  const legacy = payload.message
  return typeof legacy === 'string' ? legacy : ''
}

function importWarningLabel(entry: ImportWarningEntry): string {
  if (typeof entry === 'string') {
    return entry
  }
  const k = `importStream.warning_${entry.key}`
  return te(k) ? t(k) : entry.key
}

/** Prehliadač pri prerušení spojenia nepošle telo odpovede — zvyčajne len „Failed to fetch“. */
function formatBrowserNetworkError(e: unknown): string {
  if (e instanceof DOMException && e.name === 'AbortError') {
    return t('adminServers.cancelled')
  }
  const msg = e instanceof Error ? e.message : ''
  const m = msg.toLowerCase()
  const looksLikeTransportFailure =
    msg === 'Failed to fetch' ||
    msg === 'NetworkError when attempting to fetch resource.' ||
    m.includes('networkerror') ||
    m.includes('load failed')
  if (looksLikeTransportFailure) {
    return t('adminServers.errFetchFailedDetail')
  }
  return msg.trim() !== '' ? msg : t('adminServers.networkError')
}

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
    loadError.value = t('adminServers.loadError')
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
      formError.value = ax.response?.data?.message ?? t('adminServers.saveFailed')
    }
  } finally {
    saving.value = false
  }
}

const importDialogVisible = ref(false)
const importSource = ref<'table' | 'file'>('table')

const importProgressTitle = computed(() =>
  importSource.value === 'file' ? t('adminServers.importFromFile') : t('adminServers.importMapSqlTitle'),
)
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
const importWarnings = ref<ImportWarningEntry[]>([])
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
  const total = importTotal.value
  if (total <= 0) {
    return 0
  }
  return Math.min(100, Math.round((importCurrent.value / total) * 100))
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
    fileImportLocalError.value = t('adminServers.errSelectServer')
    return
  }
  if (!pickedSqlFile.value && !fileImportSql.value.trim()) {
    fileImportLocalError.value = t('adminServers.errSqlOrFile')
    return
  }
  const srv = servers.value.find((s) => s.id === fileImportServerId.value)
  if (!srv) {
    fileImportLocalError.value = t('adminServers.errServerMissing')
    return
  }
  const token = getAdminToken()
  if (!token) {
    fileImportLocalError.value = t('adminServers.errNotLoggedIn')
    return
  }

  fileImportDialogVisible.value = false
  importSource.value = 'file'
  importServer.value = srv
  resetImportProgressState()
  importDialogVisible.value = true
  await nextTick()
  void startImportUpload(token)
}

function openImport(row: AdminServer) {
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
      importError.value = fromErrors || j.message || t('adminServers.httpError', { status: res.status })
    } catch {
      importError.value = text.trim() || t('adminServers.httpError', { status: res.status })
    }
    return
  }

  const reader = res.body?.getReader()
  if (!reader) {
    importPhase.value = 'error'
    importError.value = t('adminServers.streamUnavailable')
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
        const line = translateImportStreamMessage(payload)
        if (phase === 'upload') {
          importPhase.value = 'upload'
        } else if (phase === 'download') {
          importPhase.value = 'download'
        } else if (phase === 'archive') {
          importPhase.value = 'import'
        } else if (phase === 'aggregate') {
          importPhase.value = 'aggregate'
        }
        importStatusText.value =
          line ||
          (phase === 'upload'
            ? t('adminServers.phaseUploadSql')
            : phase === 'download'
              ? t('adminServers.phaseDownload')
              : phase === 'archive'
                ? t('adminServers.phaseImporting')
                : phase === 'aggregate'
                  ? t('adminServers.phaseAggregate')
                  : '')
      } else if (ev === 'x_world_load') {
        importPhase.value = 'x_world_sql'
        const line = translateImportStreamMessage(payload)
        importStatusText.value =
          line ||
          t('adminServers.xWorldRows', { n: formatImportInt(Number(payload.rows) || 0) })
      } else if (ev === 'start') {
        importPhase.value = 'import'
        importTotal.value = Number(payload.total) || 0
        importStatusText.value =
          importTotal.value > 0
            ? t('adminServers.importRowsCount', { count: formatImportInt(importTotal.value) })
            : t('adminServers.importNoRows')
      } else if (ev === 'progress') {
        importPhase.value = 'import'
        importCurrent.value = Number(payload.current) || 0
        importTotal.value = Number(payload.total) || importTotal.value
        importSaved.value = Number(payload.saved) || 0
        importSkipped.value = Number(payload.skipped) || 0
        importStatusText.value = t('adminServers.importRowProgress', {
          current: formatImportInt(importCurrent.value),
          total: formatImportInt(importTotal.value),
          saved: formatImportInt(importSaved.value),
        })
      } else if (ev === 'done') {
        importPhase.value = 'done'
        const p = Number(payload.processed) || 0
        const s = Number(payload.skipped) || 0
        importDoneSummary.value = t('adminServers.importDoneSummary', {
          processed: formatImportInt(p),
          skipped: formatImportInt(s),
        })
        importStatusText.value = importDoneSummary.value
        const w = payload.import_warnings
        importWarnings.value = Array.isArray(w)
          ? w.map((x: unknown): ImportWarningEntry => {
              if (typeof x === 'string') {
                return x
              }
              if (x && typeof x === 'object' && 'key' in x) {
                const k = (x as { key: unknown }).key
                return typeof k === 'string' ? { key: k } : String(k)
              }
              return String(x)
            })
          : []
      } else if (ev === 'error') {
        importPhase.value = 'error'
        const line = translateImportStreamMessage(payload)
        importError.value =
          line ||
          (typeof payload.message === 'string' && payload.message) ||
          t('importStream.error_unknown')
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
  importStatusText.value = t('adminServers.uploadingImporting')
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
      importError.value = t('adminServers.cancelled')
    } else {
      importPhase.value = 'error'
      importError.value = formatBrowserNetworkError(e)
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
    importError.value = t('adminServers.missingAuth')
    return
  }

  importRunning.value = true
  importAbort = new AbortController()
  importPhase.value = 'download'
  importStatusText.value = t('adminServers.preparingDownload')
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
      importError.value = t('adminServers.cancelled')
    } else {
      importPhase.value = 'error'
      importError.value = formatBrowserNetworkError(e)
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
      <h1>{{ t('adminServers.pageTitle') }}</h1>
      <div class="head-actions">
        <Button
          :label="t('adminServers.importFromFile')"
          icon="pi pi-upload"
          severity="info"
          @click="openFileImportDialog"
        />
        <Button :label="t('adminServers.addServer')" icon="pi pi-plus" @click="openCreate" />
      </div>
    </div>
    <Message v-if="loadError" severity="error" class="mb-3">{{ loadError }}</Message>
    <DataTable :value="servers" :loading="loading" data-key="id" striped-rows>
      <Column field="name" :header="t('adminServers.colName')" />
      <Column field="slug" :header="t('adminServers.colSlug')" />
      <Column field="base_url" :header="t('adminServers.colBaseUrl')">
        <template #body="{ data }">
          {{ data.base_url || t('common.emDash') }}
        </template>
      </Column>
      <Column field="timezone" :header="t('adminServers.colTimezone')" />
      <Column field="is_active" :header="t('adminServers.colActive')">
        <template #body="{ data }">
          {{ data.is_active ? t('adminServers.activeYes') : t('adminServers.activeNo') }}
        </template>
      </Column>
      <Column :header="t('adminServers.colActions')" style="width: 14rem">
        <template #body="{ data }">
          <div class="row-actions">
            <Button :label="t('adminServers.edit')" size="small" text @click="openEdit(data)" />
            <Button
              :label="t('adminServers.import')"
              size="small"
              text
              severity="secondary"
              icon="pi pi-download"
              :disabled="!data.is_active"
              :title="
                !data.is_active
                  ? t('adminServers.importDisabledTooltip')
                  : t('adminServers.importEnabledTooltip')
              "
              @click="openImport(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <Dialog
      v-model:visible="dialogVisible"
      :header="editingId === null ? t('adminServers.dialogNew') : t('adminServers.dialogEdit')"
      modal
      class="w-full"
      style="max-width: 32rem"
    >
      <form class="dlg-form" @submit.prevent="save">
        <Message v-if="formError" severity="error" class="mb-2" :closable="true" @close="formError = null">
          {{ formError }}
        </Message>
        <div class="field">
          <label for="n">{{ t('adminServers.labelName') }}</label>
          <InputText id="n" v-model="form.name" class="w-full" fluid @blur="slugifyName" />
        </div>
        <div class="field">
          <label for="s">{{ t('adminServers.labelSlug') }}</label>
          <InputText id="s" v-model="form.slug" class="w-full" fluid />
          <small class="hint">{{ t('adminServers.slugHint') }}</small>
        </div>
        <div class="field">
          <label for="u">{{ t('adminServers.labelBaseUrl') }}</label>
          <InputText
            id="u"
            v-model="form.base_url"
            class="w-full"
            fluid
            :placeholder="t('adminServers.baseUrlPlaceholder')"
          />
        </div>
        <div class="field">
          <label for="tz">{{ t('adminServers.labelTimezone') }}</label>
          <InputText id="tz" v-model="form.timezone" class="w-full" fluid />
        </div>
        <div class="field row">
          <label for="act">{{ t('adminServers.labelActive') }}</label>
          <InputSwitch id="act" v-model="form.is_active" />
        </div>
        <div class="dlg-actions">
          <Button type="button" :label="t('adminServers.cancel')" severity="secondary" text @click="dialogVisible = false" />
          <Button type="submit" :label="t('adminServers.save')" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <Dialog
      v-model:visible="fileImportDialogVisible"
      :header="t('adminServers.importFromFile')"
      modal
      class="w-full"
      style="max-width: 36rem"
    >
      <Message v-if="fileImportLocalError" severity="error" class="mb-2">{{ fileImportLocalError }}</Message>
      <div class="dlg-form file-import-form">
        <div class="field">
          <label for="fi-srv">{{ t('adminServers.labelServer') }}</label>
          <Select
            id="fi-srv"
            v-model="fileImportServerId"
            :options="serverSelectOptions"
            option-label="label"
            option-value="value"
            :placeholder="t('adminServers.serverPlaceholder')"
            class="w-full"
            show-clear
          />
        </div>
        <div class="field">
          <label for="fi-date">{{ t('adminServers.labelSnapshotDate') }}</label>
          <DatePicker
            id="fi-date"
            v-model="fileImportDate"
            date-format="dd.mm.yy"
            show-icon
            class="w-full"
          />
        </div>
        <div class="field">
          <label for="fi-file">{{ t('adminServers.labelSqlFile') }}</label>
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
          <label for="fi-sql">{{ t('adminServers.labelPasteSql') }}</label>
          <Textarea
            id="fi-sql"
            v-model="fileImportSql"
            rows="10"
            class="w-full"
            auto-resize
            :placeholder="t('adminServers.sqlPlaceholder')"
          />
        </div>
        <div class="dlg-actions">
          <Button
            type="button"
            :label="t('adminServers.cancel')"
            severity="secondary"
            text
            @click="fileImportDialogVisible = false"
          />
          <Button type="button" :label="t('adminServers.importRun')" icon="pi pi-upload" @click="submitFileImport" />
        </div>
      </div>
    </Dialog>

    <Dialog
      v-model:visible="importDialogVisible"
      :header="importProgressTitle"
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
          <Message v-for="(w, i) in importWarnings" :key="i" severity="warn" class="mb-2">{{
            importWarningLabel(w)
          }}</Message>
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
            :label="t('adminServers.close')"
            severity="secondary"
            text
            :disabled="importRunning"
            @click="closeImportDialog"
          />
          <Button
            v-if="importSource === 'table'"
            type="button"
            :label="t('adminServers.startImport')"
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
