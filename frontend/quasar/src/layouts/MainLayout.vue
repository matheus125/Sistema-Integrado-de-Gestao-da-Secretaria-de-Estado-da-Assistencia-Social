<template>
  <q-layout view="lHh Lpr lFf">
    <q-header class="app-header">
      <q-toolbar class="q-px-md">
        <q-btn flat dense round icon="menu" aria-label="Abrir menu" @click="toggleLeftDrawer" />

        <q-toolbar-title class="text-weight-bold">
          SIGES
          <span class="text-caption text-weight-regular gt-xs q-ml-sm">
            Secretaria de Estado da Assistencia Social
          </span>
        </q-toolbar-title>

        <q-input
          v-model="searchTerm"
          dense
          outlined
          dark
          class="global-search gt-sm"
          placeholder="Buscar no SIGES"
          @keyup.enter="runSearch"
        >
          <template #prepend>
            <q-icon name="search" />
          </template>
          <template #append>
            <q-btn flat dense round icon="arrow_forward" @click="runSearch">
              <q-tooltip>Buscar</q-tooltip>
            </q-btn>
          </template>
        </q-input>

        <q-btn flat round icon="notifications" :loading="notificationsLoading" @click="openNotifications">
          <q-badge v-if="notifications.length" color="warning" floating>{{ notifications.length }}</q-badge>
          <q-tooltip>Notificacoes</q-tooltip>
        </q-btn>

        <q-btn flat round icon="account_circle">
          <q-menu>
            <q-list style="min-width: 240px">
              <q-item>
                <q-item-section>
                  <q-item-label>{{ auth.userName }}</q-item-label>
                  <q-item-label caption>{{ auth.roles.join(', ') || 'Sem perfil' }}</q-item-label>
                </q-item-section>
              </q-item>
              <q-separator />
              <q-item clickable v-close-popup @click="logout">
                <q-item-section avatar>
                  <q-icon name="logout" />
                </q-item-section>
                <q-item-section>Sair</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
          <q-tooltip>Perfil do usuario</q-tooltip>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" show-if-above bordered :width="286">
      <div class="drawer-brand q-pa-md">
        <div class="text-overline">Plataforma integrada</div>
        <div class="text-h6 text-weight-bold">Gestao SEAS</div>
      </div>

      <q-list padding>
        <q-item
          v-for="item in visibleNavigation"
          :key="item.label"
          clickable
          v-ripple
          :active="item.active"
          active-class="active-nav"
          @click="selectModule(item)"
        >
          <q-item-section avatar>
            <q-icon :name="item.icon" />
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ item.label }}</q-item-label>
            <q-item-label caption>{{ item.caption }}</q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>

    <q-dialog v-model="showSearch">
      <q-card class="dialog-card">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">Resultado da busca</div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-card-section>
          <q-list v-if="searchResults.length" separator>
            <q-item v-for="result in searchResults" :key="`${result.group}-${result.id}`">
              <q-item-section avatar>
                <q-icon :name="result.icon" color="primary" />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ result.title }}</q-item-label>
                <q-item-label caption>{{ result.group }} - {{ result.subtitle || 'sem complemento' }}</q-item-label>
                <q-item-label v-if="result.group === 'tickets'" caption>{{ result.category || 'Sem categoria' }} · {{ result.department || 'Sem setor' }} · {{ result.technician || 'Sem técnico' }}<span v-if="result.asset_number"> · Patrimônio {{ result.asset_number }}</span></q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
          <q-banner v-else class="bg-grey-2 text-grey-8">
            Nenhum resultado encontrado para esta busca.
          </q-banner>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showNotifications">
      <q-card class="dialog-card">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">Notificacoes</div>
          <div class="row q-gutter-xs">
            <q-btn flat round dense icon="refresh" :loading="notificationsLoading" @click="loadNotifications">
              <q-tooltip>Atualizar notificacoes</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="close" v-close-popup />
          </div>
        </q-card-section>
        <q-separator />
        <q-list v-if="notifications.length" separator>
          <q-item v-for="item in notifications" :key="item.id" clickable v-ripple @click="goToNotification(item)">
            <q-item-section avatar>
              <q-icon :name="item.icon" :color="item.color" />
            </q-item-section>
            <q-item-section>
              <q-item-label>{{ item.title }}</q-item-label>
              <q-item-label caption>{{ item.detail }}</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
        <q-card-section v-else>
          <q-banner class="bg-grey-2 text-grey-8">Nenhuma notificacao pendente.</q-banner>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-layout>
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
const leftDrawerOpen = ref(false)
const searchTerm = ref('')
const showSearch = ref(false)
const showNotifications = ref(false)
const rawSearchResults = ref({})
const notifications = ref([])
const notificationsLoading = ref(false)

const navigation = ref([
  { label: 'Dashboard', caption: 'Indicadores e alertas', icon: 'dashboard', to: '/', permission: 'dashboard.visualizar', active: true },
  { label: 'Administracao', caption: 'Usuarios, setores e perfis', icon: 'admin_panel_settings', to: '/administracao', permission: 'administracao.visualizar' },
  { label: 'Transporte', caption: 'Frota e solicitacoes', icon: 'directions_car', to: '/transporte', permission: 'transporte.visualizar' },
  { label: 'Materiais e Patrimonio', caption: 'Estoque e bens', icon: 'inventory_2', to: '/materiais', permission: 'materiais-patrimonio.visualizar' },
  { label: 'Informatica', caption: 'Help Desk e SLA', icon: 'support_agent', to: '/informatica', permission: 'informatica.visualizar' },
  { label: 'Meus Chamados', caption: 'Acompanhar minhas solicitações', icon: 'assignment', to: '/informatica/meus-chamados', permission: 'informatica.visualizar' },
  { label: 'Manutenção Preventiva', caption: 'Planos, alertas e chamados automáticos', icon: 'event_repeat', to: '/informatica/manutencao-preventiva', permission: 'informatica.visualizar' },
  { label: 'Relatorios', caption: 'PDF, Excel, CSV', icon: 'summarize', to: '/relatorios', permission: 'relatorios.visualizar' },
  { label: 'Auditoria', caption: 'Historico completo', icon: 'manage_search', to: '/auditoria', permission: 'auditoria.visualizar' },
  { label: 'Configuracoes', caption: 'Parametros do sistema', icon: 'settings', to: '/configuracoes', permission: 'configuracoes.visualizar' }
])

const groupIcons = {
  vehicles: 'directions_car',
  drivers: 'badge',
  products: 'inventory_2',
  assets: 'business_center',
  tickets: 'support_agent',
  users: 'person',
  departments: 'apartment'
}

const searchResults = computed(() => Object.entries(rawSearchResults.value).flatMap(([group, items]) => (
  (items || []).map(item => ({ ...item, group, icon: groupIcons[group] || 'search' }))
)))
const visibleNavigation = computed(() => navigation.value.filter(item => !item.permission || auth.can(item.permission)))

onMounted(loadNotifications)

function toggleLeftDrawer () {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

function selectModule(selected) {
  navigation.value = navigation.value.map(item => ({ ...item, active: item.label === selected.label }))

  if (selected.to) {
    router.push(selected.to)
    return
  }

  $q.notify({ type: 'info', message: `${selected.label}: modulo em preparacao para CRUD completo.` })
}

async function runSearch() {
  if (searchTerm.value.trim().length < 2) {
    $q.notify({ type: 'warning', message: 'Digite pelo menos 2 caracteres para buscar.' })
    return
  }

  try {
    rawSearchResults.value = await apiFetch(`/search?q=${encodeURIComponent(searchTerm.value.trim())}`)
    showSearch.value = true
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  }
}

async function openNotifications() {
  showNotifications.value = true
  await loadNotifications()
}

async function loadNotifications() {
  if (!auth.isAuthenticated) return

  notificationsLoading.value = true

  try {
    const payload = await apiFetch('/notifications')
    notifications.value = payload.data || []
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    notificationsLoading.value = false
  }
}

function goToNotification(item) {
  showNotifications.value = false
  if (item.to) {
    router.push(item.to)
  }
}

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>
