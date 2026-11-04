<template>
  <q-page class="module-page">
    <section class="module-header">
      <div>
        <div class="text-overline text-primary">Gerência de Informática</div>
        <h1>Manutenção preventiva</h1>
        <p>Planeje ciclos de manutenção por patrimônio e gere chamados automaticamente antes do vencimento.</p>
      </div>
      <div class="action-row">
        <q-btn v-if="canEdit" color="warning" icon="bolt" label="Gerar chamados" :loading="generating" @click="generate" />
        <q-btn v-if="canEdit" color="primary" icon="add" label="Novo plano" unelevated @click="openPlan()" />
        <q-btn color="secondary" icon="refresh" label="Atualizar" unelevated :loading="loading" @click="load" />
      </div>
    </section>

    <section class="content-shell module-content">
      <div class="metric-grid q-mb-md">
        <q-card flat bordered class="metric-card"><q-card-section><q-icon name="event_repeat" color="primary" size="28px" /><div class="metric-value">{{ summary.total || 0 }}</div><div class="metric-label">Planos ativos</div></q-card-section></q-card>
        <q-card flat bordered class="metric-card"><q-card-section><q-icon name="warning" color="negative" size="28px" /><div class="metric-value">{{ summary.overdue || 0 }}</div><div class="metric-label">Em atraso</div></q-card-section></q-card>
        <q-card flat bordered class="metric-card"><q-card-section><q-icon name="schedule" color="warning" size="28px" /><div class="metric-value">{{ summary.due_soon || 0 }}</div><div class="metric-label">Próximas (7 dias)</div></q-card-section></q-card>
      </div>

      <q-card flat bordered>
        <q-card-section class="row items-center q-gutter-sm">
          <q-input v-model="filters.search" dense outlined debounce="400" placeholder="Buscar plano" @update:model-value="load" />
          <q-select v-model="filters.status" dense outlined clearable label="Status" :options="statusOptions" emit-value map-options @update:model-value="load" />
        </q-card-section>
        <q-table flat :rows="plans" :columns="columns" row-key="id" :loading="loading" no-data-label="Nenhum plano cadastrado">
          <template #body-cell-asset="props"><q-td :props="props">{{ props.row.asset?.asset_number }} — {{ props.row.asset?.description }}</q-td></template>
          <template #body-cell-status="props"><q-td :props="props"><q-badge :color="props.row.status === 'ativo' ? 'positive' : 'grey'" outline>{{ props.row.status }}</q-badge></q-td></template>
          <template #body-cell-actions="props"><q-td :props="props" class="q-gutter-xs"><q-btn v-if="canEdit" flat round dense icon="check" color="positive" @click="complete(props.row)"><q-tooltip>Registrar manutenção concluída</q-tooltip></q-btn><q-btn v-if="canEdit" flat round dense icon="edit" color="primary" @click="openPlan(props.row)"><q-tooltip>Editar plano</q-tooltip></q-btn></q-td></template>
        </q-table>
      </q-card>
    </section>

    <q-dialog v-model="dialog">
      <q-card class="dialog-card request-dialog">
        <q-card-section class="text-h6">{{ form.id ? 'Editar plano' : 'Novo plano de manutenção' }}</q-card-section>
        <q-separator />
        <q-form @submit="save">
          <q-card-section class="form-grid">
            <q-select v-model="form.asset_id" outlined label="Patrimônio" :options="assetOptions" emit-value map-options use-input input-debounce="300" :rules="[required]" />
            <q-input v-model="form.name" outlined label="Nome do plano" :rules="[required]" />
            <q-input v-model.number="form.frequency_days" outlined type="number" label="Periodicidade (dias)" :rules="[required]" />
            <q-input v-model="form.next_maintenance_at" outlined type="date" label="Próxima manutenção" :rules="[required]" />
            <q-input v-model="form.last_maintenance_at" outlined type="date" label="Última manutenção" />
            <q-input v-model.number="form.lead_days" outlined type="number" label="Alerta antecipado (dias)" />
            <q-select v-model="form.status" outlined label="Status" :options="statusOptions" emit-value map-options />
            <q-toggle v-model="form.auto_create_ticket" label="Gerar chamado automaticamente" />
            <q-input v-model="form.description" outlined type="textarea" label="Procedimento / descrição" class="span-2" />
          </q-card-section>
          <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup /><q-btn type="submit" color="primary" label="Salvar" unelevated /></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { apiFetch } from '@/services/api'
import { useAuthStore } from '@/stores/auth-store'

const $q = useQuasar()
const auth = useAuthStore()
const loading = ref(false)
const generating = ref(false)
const dialog = ref(false)
const plans = ref([])
const assets = ref([])
const summary = ref({})
const filters = reactive({ search: '', status: null })
const form = reactive(defaultForm())
const statusOptions = [{ label: 'Ativo', value: 'ativo' }, { label: 'Pausado', value: 'pausado' }]
const canEdit = computed(() => auth.can('informatica.editar'))
const assetOptions = computed(() => assets.value.map(asset => ({ label: `${asset.asset_number} — ${asset.description}`, value: asset.id })))
const columns = [
  { name: 'asset', label: 'Patrimônio', field: 'asset', align: 'left' },
  { name: 'name', label: 'Plano', field: 'name', align: 'left' },
  { name: 'frequency_days', label: 'Periodicidade', field: row => `${row.frequency_days} dias`, align: 'left' },
  { name: 'next_maintenance_at', label: 'Próxima', field: 'next_maintenance_at', align: 'left' },
  { name: 'status', label: 'Status', field: 'status', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' }
]

function defaultForm () { return { id: null, asset_id: null, name: '', description: '', frequency_days: 180, last_maintenance_at: null, next_maintenance_at: new Date().toISOString().slice(0, 10), lead_days: 7, status: 'ativo', auto_create_ticket: true } }
function required (value) { return value !== null && value !== undefined && String(value).trim() !== '' || 'Campo obrigatório' }
function openPlan (row = null) { Object.assign(form, row ? { ...defaultForm(), ...row } : defaultForm()); dialog.value = true }
async function load () {
  loading.value = true
  try {
    const [plansData, summaryData, assetsData] = await Promise.all([
      apiFetch(`/it/preventive-maintenance?search=${encodeURIComponent(filters.search)}${filters.status ? `&status=${filters.status}` : ''}`),
      apiFetch('/it/preventive-maintenance/summary'),
      apiFetch('/materials/assets?per_page=100')
    ])
    plans.value = plansData.data || []
    summary.value = summaryData
    assets.value = assetsData.data || []
  } catch (error) { $q.notify({ type: 'negative', message: error.message }) } finally { loading.value = false }
}
async function save () {
  try { await apiFetch(form.id ? `/it/preventive-maintenance/${form.id}` : '/it/preventive-maintenance', { method: form.id ? 'PUT' : 'POST', body: JSON.stringify(form) }); dialog.value = false; $q.notify({ type: 'positive', message: 'Plano salvo.' }); await load() } catch (error) { $q.notify({ type: 'negative', message: error.message }) }
}
async function complete (row) {
  try { await apiFetch(`/it/preventive-maintenance/${row.id}/complete`, { method: 'POST', body: JSON.stringify({}) }); $q.notify({ type: 'positive', message: 'Manutenção registrada.' }); await load() } catch (error) { $q.notify({ type: 'negative', message: error.message }) }
}
async function generate () {
  generating.value = true
  try { const result = await apiFetch('/it/preventive-maintenance/generate', { method: 'POST', body: JSON.stringify({}) }); $q.notify({ type: 'positive', message: `${result.generated || 0} chamado(s) gerado(s).` }); await load() } catch (error) { $q.notify({ type: 'negative', message: error.message }) } finally { generating.value = false }
}
onMounted(load)
</script>
