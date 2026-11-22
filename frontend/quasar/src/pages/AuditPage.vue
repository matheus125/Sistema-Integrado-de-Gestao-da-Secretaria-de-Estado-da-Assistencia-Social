<template>
  <q-page class="module-page">
    <section class="module-header">
      <div>
        <div class="text-overline text-primary">Auditoria</div>
        <h1>Historico completo</h1>
        <p>Consulte logins, cadastros, alteracoes, aprovacoes, cancelamentos e movimentacoes realizadas no SIGES.</p>
      </div>
      <div class="action-row">
        <q-btn color="primary" icon="refresh" label="Atualizar" unelevated :loading="loading" @click="loadAll" />
      </div>
    </section>

    <section class="content-shell module-content">
      <q-table
        flat
        bordered
        title="Eventos auditados"
        :rows="logs"
        :columns="columns"
        row-key="id"
        :loading="loading"
        no-data-label="Nenhum evento encontrado"
      >
        <template #top>
          <div class="audit-filters">
            <q-input v-model="filters.search" dense outlined debounce="400" label="Buscar" @update:model-value="loadAll">
              <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="filters.module" dense outlined clearable label="Modulo" :options="moduleOptions" @update:model-value="loadAll" />
            <q-select v-model="filters.user_id" dense outlined clearable label="Usuario" :options="userOptions" emit-value map-options @update:model-value="loadAll" />
            <q-input v-model="filters.date_from" dense outlined type="date" label="De" @update:model-value="loadAll" />
            <q-input v-model="filters.date_to" dense outlined type="date" label="Ate" @update:model-value="loadAll" />
          </div>
        </template>

        <template #body-cell-module="props">
          <q-td :props="props">
            <q-badge color="primary" outline>{{ props.row.module || 'sistema' }}</q-badge>
          </q-td>
        </template>

        <template #body-cell-causer="props">
          <q-td :props="props">
            <div>{{ props.row.causer?.name || 'Sistema' }}</div>
            <div class="text-caption text-grey-7">{{ props.row.causer?.email || '-' }}</div>
          </q-td>
        </template>

        <template #body-cell-subject="props">
          <q-td :props="props">{{ props.row.subject_type || '-' }} #{{ props.row.subject_id || '-' }}</q-td>
        </template>

        <template #body-cell-actions="props">
          <q-td :props="props">
            <q-btn flat round dense icon="visibility" aria-label="Ver detalhes da auditoria" @click="inspect(props.row)">
              <q-tooltip>Ver detalhes</q-tooltip>
            </q-btn>
          </q-td>
        </template>
      </q-table>
    </section>

    <q-dialog v-model="detailDialog">
      <q-card class="dialog-card audit-detail-dialog">
        <q-card-section class="row items-center justify-between">
          <div>
            <div class="text-h6">Evento #{{ selected?.id }}</div>
            <div class="text-caption text-grey-7">{{ formatDateTime(selected?.created_at) }}</div>
          </div>
          <q-btn flat round dense icon="close" aria-label="Fechar detalhes da auditoria" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section v-if="selected" class="q-gutter-md">
          <div class="audit-detail-grid">
            <div>
              <div class="text-caption text-grey-7">Modulo</div>
              <div class="text-weight-medium">{{ selected.module || 'sistema' }}</div>
            </div>
            <div>
              <div class="text-caption text-grey-7">Usuario</div>
              <div class="text-weight-medium">{{ selected.causer?.name || 'Sistema' }}</div>
            </div>
            <div>
              <div class="text-caption text-grey-7">Registro</div>
              <div class="text-weight-medium">{{ selected.subject_type || '-' }} #{{ selected.subject_id || '-' }}</div>
            </div>
          </div>
          <q-banner class="bg-grey-2 text-grey-9">{{ selected.description }}</q-banner>
          <q-input :model-value="prettyProperties" readonly outlined type="textarea" autogrow label="Propriedades" />
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { apiFetch } from '@/services/api'
import { formatDateTime } from '@/composables/useDateFormat'

const $q = useQuasar()
const loading = ref(false)
const logs = ref([])
const options = ref({ modules: [], users: [] })
const detailDialog = ref(false)
const selected = ref(null)
const filters = reactive({
  search: '',
  module: null,
  user_id: null,
  date_from: '',
  date_to: ''
})

const columns = [
  { name: 'created_at', label: 'Data/hora', field: row => formatDateTime(row.created_at), align: 'left', sortable: true },
  { name: 'module', label: 'Modulo', field: 'module', align: 'left' },
  { name: 'description', label: 'Acao', field: 'description', align: 'left' },
  { name: 'causer', label: 'Usuario', field: 'causer', align: 'left' },
  { name: 'subject', label: 'Registro', field: 'subject', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

const moduleOptions = computed(() => options.value.modules)
const userOptions = computed(() => options.value.users.map(user => ({ label: `${user.name} - ${user.email}`, value: user.id })))
const prettyProperties = computed(() => JSON.stringify(selected.value?.properties || {}, null, 2))

onMounted(loadAll)

async function loadAll() {
  loading.value = true

  try {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== null && value !== '') params.set(key, value)
    })

    const [logData, optionData] = await Promise.all([
      apiFetch(`/audit/logs?${params.toString()}`),
      apiFetch('/audit/options')
    ])

    logs.value = logData.data || []
    options.value = optionData
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    loading.value = false
  }
}

function inspect(row) {
  selected.value = row
  detailDialog.value = true
}

</script>
