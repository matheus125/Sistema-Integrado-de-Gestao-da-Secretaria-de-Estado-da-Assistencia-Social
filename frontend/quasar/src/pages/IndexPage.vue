<template>
  <q-page class="dashboard-page">
    <section class="page-band">
      <div class="dashboard-grid">
        <div class="dashboard-heading">
          <div class="text-overline text-primary">Visao geral</div>
          <h1>SIGES</h1>
          <p>
            Painel operacional das gerencias da SEAS com indicadores por setor,
            filas de aprovacao e alertas administrativos.
          </p>
          <div class="action-row">
            <q-btn v-if="auth.can('transporte.cadastrar')" color="primary" icon="add_road" label="Solicitar veiculo" unelevated @click="router.push('/transporte')" />
            <q-btn v-if="auth.can('informatica.cadastrar')" color="secondary" icon="support_agent" label="Abrir chamado" unelevated @click="router.push('/informatica')" />
            <q-btn v-if="auth.can('materiais-patrimonio.cadastrar')" color="accent" icon="inventory_2" label="Entrada material" unelevated @click="router.push('/materiais')" />
          </div>
        </div>

        <div class="status-strip">
          <div v-for="item in executiveStatus" :key="item.label" class="status-tile">
            <q-icon :name="item.icon" :color="item.color" size="28px" />
            <div>
              <strong>{{ item.value }}</strong>
              <span>{{ item.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="content-shell">
      <div class="section-toolbar">
        <div>
          <div class="panel-title">Indicadores em tempo real</div>
          <div class="text-caption text-grey-7">Dados carregados da API protegida por Sanctum</div>
        </div>
        <q-btn flat round icon="refresh" :loading="loading" @click="loadDashboard">
          <q-tooltip>Atualizar dashboard</q-tooltip>
        </q-btn>
      </div>

      <div class="metric-grid">
        <q-card v-for="metric in metrics" :key="metric.label" flat bordered class="metric-card">
          <q-card-section>
            <div class="row items-start justify-between no-wrap">
              <div>
                <div class="text-caption text-grey-7">{{ metric.module }}</div>
                <div class="metric-value">{{ metric.value }}</div>
                <div class="metric-label">{{ metric.label }}</div>
              </div>
              <q-icon :name="metric.icon" :color="metric.color" size="34px" />
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div v-if="chartPanels.length" class="chart-grid">
        <q-card v-for="chart in chartPanels" :key="chart.key" flat bordered class="chart-panel">
          <q-card-section>
            <div class="row items-center justify-between q-mb-md">
              <div>
                <div class="panel-title">{{ chart.title }}</div>
                <div class="text-caption text-grey-7">{{ chart.caption }}</div>
              </div>
              <q-icon :name="chart.icon" :color="chart.color" size="28px" />
            </div>

            <div class="chart-bars">
              <div v-for="item in chart.items" :key="item.key" class="chart-row">
                <div class="chart-label">{{ item.label }}</div>
                <div class="chart-track">
                  <div class="chart-fill" :style="{ width: `${item.percent}%`, background: chart.fill }" />
                </div>
                <strong>{{ item.value }}</strong>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="work-grid">
        <q-card flat bordered class="work-panel">
          <q-card-section class="row items-center justify-between">
            <div>
              <div class="panel-title">Solicitacoes em andamento</div>
              <div class="text-caption text-grey-7">Transporte, materiais e informatica</div>
            </div>
            <q-btn flat round icon="filter_alt" @click="filterOpen = !filterOpen">
              <q-tooltip>Filtrar</q-tooltip>
            </q-btn>
          </q-card-section>
          <q-slide-transition>
            <q-card-section v-show="filterOpen" class="q-pt-none">
              <q-option-group v-model="selectedStatuses" :options="statusOptions" type="checkbox" inline />
            </q-card-section>
          </q-slide-transition>
          <q-separator />
          <q-list separator>
            <q-item v-for="request in filteredRequests" :key="request.id" clickable v-ripple @click="inspectItem(request)">
              <q-item-section avatar>
                <q-icon :name="request.icon" :color="request.color" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ request.title }}</q-item-label>
                <q-item-label caption>{{ request.detail }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-badge :color="request.badgeColor" outline>{{ request.status }}</q-badge>
              </q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-if="!filteredRequests.length" class="text-grey-7">
            Nenhuma solicitacao em andamento para seu perfil.
          </q-card-section>
        </q-card>

        <q-card flat bordered class="work-panel">
          <q-card-section class="row items-center justify-between">
            <div>
              <div class="panel-title">Alertas</div>
              <div class="text-caption text-grey-7">Itens que exigem acompanhamento</div>
            </div>
            <q-btn flat round icon="refresh" :loading="alertsLoading" @click="loadAlerts">
              <q-tooltip>Atualizar alertas</q-tooltip>
            </q-btn>
          </q-card-section>
          <q-separator />
          <q-list separator>
            <q-item v-for="alert in alerts" :key="alert.id" clickable v-ripple @click="inspectItem(alert)">
              <q-item-section avatar>
                <q-icon :name="alert.icon" :color="alert.color" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ alert.title }}</q-item-label>
                <q-item-label caption>{{ alert.detail }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-if="!alerts.length" class="text-grey-7">
            Nenhum alerta pendente.
          </q-card-section>
        </q-card>
      </div>
    </section>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { apiFetch } from '@/services/api'
import { useAuthStore } from '@/stores/auth-store'

const $q = useQuasar()
const router = useRouter()
const auth = useAuthStore()
const loading = ref(false)
const alertsLoading = ref(false)
const dashboard = ref(null)
const alerts = ref([])
const filterOpen = ref(false)
const selectedStatuses = ref([])

const executiveStatus = computed(() => [
  { label: 'modulos ativos', value: '8', icon: 'apps', color: 'primary' },
  { label: 'perfis carregados', value: '5', icon: 'admin_panel_settings', color: 'secondary' },
  { label: 'acoes auditadas', value: '100%', icon: 'verified_user', color: 'positive' }
])

const metrics = computed(() => {
  const data = dashboard.value

  return [
    { module: 'Transporte', label: 'veiculos cadastrados', value: data?.transportes?.veiculos ?? '-', icon: 'directions_car', color: 'primary' },
    { module: 'Transporte', label: 'solicitacoes abertas', value: data?.transportes?.solicitacoes_abertas ?? '-', icon: 'task_alt', color: 'warning' },
    { module: 'Materiais', label: 'produtos abaixo do minimo', value: data?.materiais_patrimonio?.produtos_abaixo_minimo ?? '-', icon: 'inventory', color: 'negative' },
    { module: 'Informatica', label: 'chamados abertos', value: data?.informatica?.chamados_abertos ?? '-', icon: 'support_agent', color: 'secondary' }
  ].filter(metric => metric.value !== '-')
})

const chartConfig = {
  solicitacoes_transporte: {
    title: 'Solicitacoes de transporte',
    caption: 'Distribuicao por situacao',
    icon: 'route',
    color: 'primary',
    fill: '#1f6feb'
  },
  patrimonios: {
    title: 'Patrimonios',
    caption: 'Situacao dos bens do setor',
    icon: 'business_center',
    color: 'accent',
    fill: '#4d8f68'
  },
  chamados_por_prioridade: {
    title: 'Chamados de informatica',
    caption: 'Distribuicao por prioridade',
    icon: 'support_agent',
    color: 'secondary',
    fill: '#7c3aed'
  },
  chamados_por_tipo: {
    title: 'Tipos de chamados',
    caption: 'Distribuicao por tipo',
    icon: 'category',
    color: 'primary',
    fill: '#0f766e'
  }
}

const chartPanels = computed(() => {
  const charts = dashboard.value?.charts || {}

  return Object.entries(charts).map(([key, items]) => {
    const config = chartConfig[key] || {
      title: key,
      caption: 'Indicadores',
      icon: 'bar_chart',
      color: 'primary',
      fill: '#1f6feb'
    }
    const max = Math.max(...items.map(item => item.value), 1)

    return {
      key,
      ...config,
      items: items.map(item => ({
        ...item,
        percent: item.value > 0 ? Math.max((item.value / max) * 100, 8) : 0
      }))
    }
  })
})

const requests = computed(() => dashboard.value?.work_items || [])

const statusOptions = computed(() => {
  const statuses = [...new Set(requests.value.map(item => item.status))]

  return statuses.map(status => ({ label: status, value: status }))
})

const filteredRequests = computed(() => {
  if (!selectedStatuses.value.length) {
    return requests.value
  }

  return requests.value.filter(item => selectedStatuses.value.includes(item.status))
})

onMounted(async () => {
  await Promise.all([loadDashboard(), loadAlerts()])
})

async function loadDashboard() {
  loading.value = true

  try {
    dashboard.value = await apiFetch('/dashboard')
    selectedStatuses.value = []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    loading.value = false
  }
}

async function loadAlerts() {
  alertsLoading.value = true

  try {
    const payload = await apiFetch('/notifications')
    alerts.value = payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    alertsLoading.value = false
  }
}

function inspectItem(item) {
  if (item.to) {
    router.push(item.to)
    return
  }

  $q.notify({ type: 'info', message: item.title })
}
</script>
