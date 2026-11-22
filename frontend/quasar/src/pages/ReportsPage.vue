<template>
  <q-page class="module-page">
    <section class="module-header">
      <div>
        <div class="text-overline text-primary">Relatorios</div>
        <h1>Exportacoes gerenciais</h1>
        <p>Gere relatorios por periodo e situacao, com pre-visualizacao, PDF, Excel, CSV e impressao.</p>
      </div>
      <div class="action-row">
        <q-btn color="primary" icon="visibility" label="Visualizar" unelevated :loading="loading" @click="loadPreview" />
        <q-btn v-if="canPrint" color="secondary" icon="print" label="Imprimir" unelevated @click="printReport" />
      </div>
    </section>

    <section class="content-shell module-content">
      <q-card flat bordered class="work-panel q-mb-md">
        <q-card-section class="report-filters">
          <q-select v-model="filters.report" v-bind="serverErrorProps('report')" outlined label="Relatorio" :options="reportOptions" emit-value map-options @update:model-value="loadPreview" />
          <q-input v-model="filters.date_from" v-bind="serverErrorProps('date_from')" outlined type="date" label="De" />
          <q-input v-model="filters.date_to" v-bind="serverErrorProps('date_to')" outlined type="date" label="Ate" />
          <q-input v-model="filters.status" v-bind="serverErrorProps('status')" outlined label="Status/Tipo/Modulo" clearable />
          <q-select
            v-if="filters.report === 'it_tickets'"
            v-model="filters.type"
            v-bind="serverErrorProps('type')"
            outlined
            label="Tipo de chamado"
            :options="itTicketTypeOptions"
            emit-value
            map-options
            clearable
            @update:model-value="loadPreview"
          />
          <div class="row q-gutter-sm items-center">
            <q-btn v-if="canExport" color="primary" icon="table_view" label="CSV" unelevated @click="download('csv')" />
            <q-btn v-if="canExport" color="positive" icon="grid_on" label="Excel" unelevated @click="download('xlsx')" />
            <q-btn v-if="canPdf" color="negative" icon="picture_as_pdf" label="PDF" unelevated @click="download('pdf')" />
          </div>
        </q-card-section>
      </q-card>

      <q-table
        id="report-print-area"
        flat
        bordered
        :title="preview.title || 'Previa do relatorio'"
        :rows="tableRows"
        :columns="tableColumns"
        row-key="__index"
        :loading="loading"
        no-data-label="Nenhum registro encontrado"
      >
        <template #top-right>
          <q-badge color="primary" outline>{{ preview.total || 0 }} registros</q-badge>
        </template>
      </q-table>
    </section>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { formatDate, formatDateTime } from '@/composables/useDateFormat'
import { apiDownload, apiFetch } from '@/services/api'
import { clearServerValidationErrors, serverErrorProps } from '@/services/server-validation'
import { useAuthStore } from '@/stores/auth-store'

const $q = useQuasar()
const auth = useAuthStore()
const loading = ref(false)
const options = ref({ reports: [] })
const preview = ref({ title: '', headings: [], rows: [], total: 0 })
const filters = reactive({
  report: 'transport_requests',
  date_from: '',
  date_to: '',
  status: '',
  type: ''
})

const reportOptions = computed(() => options.value.reports)
const itTicketTypeOptions = computed(() => options.value.it_ticket_types || [])
const canExport = computed(() => auth.can('relatorios.exportar'))
const canPdf = computed(() => auth.can('relatorios.emitir-pdf'))
const canPrint = computed(() => canExport.value || canPdf.value)
const tableColumns = computed(() => (preview.value.headings || []).map((heading, index) => ({
  name: `col_${index}`,
  label: heading,
  field: row => formatReportValue(row.values[index]),
  align: 'left'
})))
const tableRows = computed(() => (preview.value.rows || []).map((values, index) => ({ __index: index, values })))

function formatReportValue (value) {
  const text = String(value ?? '')

  if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return formatDate(text)
  if (/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/.test(text)) return formatDateTime(text)

  return value ?? ''
}

onMounted(async () => {
  await loadOptions()
  await loadPreview()
})

async function loadOptions() {
  try {
    options.value = await apiFetch('/reports/options')
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function loadPreview() {
  loading.value = true
  clearServerValidationErrors()

  try {
    preview.value = await apiFetch(`/reports/preview?${params().toString()}`)
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    loading.value = false
  }
}

async function download(format) {
  try {
    clearServerValidationErrors()
    const query = params()
    query.set('format', format)
    await apiDownload(`/reports/export?${query.toString()}`, `${filters.report}.${format}`)
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

function printReport() {
  window.print()
}

function params() {
  const query = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== null && value !== '') query.set(key, value)
  })
  return query
}
</script>
