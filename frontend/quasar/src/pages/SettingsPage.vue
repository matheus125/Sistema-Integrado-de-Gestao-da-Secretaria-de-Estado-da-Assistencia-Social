<template>
  <q-page class="module-page">
    <section class="module-header">
      <div>
        <div class="text-overline text-primary">Configuracoes</div>
        <h1>Parametros do sistema</h1>
        <p>Centralize dados institucionais, Help Desk, SLA, notificacoes, automacoes e integracoes do SIGES.</p>
      </div>
      <div class="action-row">
        <q-btn v-if="canEdit" color="primary" icon="save" label="Salvar" unelevated :loading="saving" @click="saveSettings" />
        <q-btn color="secondary" icon="refresh" label="Recarregar" unelevated :loading="loading" @click="loadSettings" />
      </div>
    </section>

    <section class="content-shell module-content">
      <q-tabs v-model="tab" dense align="left" active-color="primary" indicator-color="primary">
        <q-tab v-for="group in groupNames" :key="group" :name="group" :label="groupLabel(group)" :icon="groupIcon(group)" />
      </q-tabs>

      <q-separator />

      <q-tab-panels v-model="tab" animated class="module-panels">
        <q-tab-panel v-for="group in groupNames" :key="group" :name="group">
          <q-banner v-if="group === 'integracoes'" class="bg-grey-2 text-grey-9 q-mb-md">
            Webhook WhatsApp: <strong>{{ configuredWebhookUrl || webhookUrl }}</strong>
            <div class="text-caption text-grey-7">Use este caminho no painel da Meta. Em ambiente local, publique a porta 8000 com ngrok ou tunnel equivalente.</div>
          </q-banner>
          <div class="settings-grid">
            <q-card v-for="setting in groupedSettings[group]" :key="setting.key" flat bordered class="setting-card">
              <q-card-section>
                <div class="row items-start justify-between no-wrap q-mb-sm">
                  <div>
                    <div class="text-weight-bold">{{ setting.label }}</div>
                    <div v-if="setting.description" class="text-caption text-grey-7">{{ setting.description }}</div>
                  </div>
                  <q-badge v-if="setting.is_secret" color="warning" outline>sigiloso</q-badge>
                </div>

                <q-toggle
                  v-if="setting.type === 'boolean'"
                  v-model="setting.value"
                  :label="setting.value ? 'Habilitado' : 'Desabilitado'"
                />
                <q-input
                  v-else-if="setting.type === 'number'"
                  v-model.number="setting.value"
                  outlined
                  type="number"
                  dense
                />
                <q-input
                  v-else
                  v-model="setting.value"
                  outlined
                  dense
                  :type="setting.type === 'password' ? 'password' : 'text'"
                  :placeholder="setting.has_secret ? 'Valor sigiloso ja cadastrado' : ''"
                />
              </q-card-section>
            </q-card>
          </div>
        </q-tab-panel>
      </q-tab-panels>
    </section>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { API_URL, apiFetch } from '@/services/api'
import { useAuthStore } from '@/stores/auth-store'

const $q = useQuasar()
const auth = useAuthStore()
const loading = ref(false)
const saving = ref(false)
const groupedSettings = ref({})
const tab = ref('institucional')

const groupNames = computed(() => Object.keys(groupedSettings.value))
const canEdit = computed(() => auth.can('configuracoes.editar'))
const webhookUrl = computed(() => `${API_URL.replace('/api', '')}/api/webhooks/whatsapp`)
const configuredWebhookUrl = computed(() => {
  const item = [...(groupedSettings.value.whatsapp || []), ...(groupedSettings.value.integracoes || [])].find(setting => setting.key === 'whatsapp_business_webhook_url')
  return item?.value || ''
})

onMounted(loadSettings)

async function loadSettings() {
  loading.value = true

  try {
    const data = await apiFetch('/settings')
    groupedSettings.value = data.groups || {}
    tab.value = groupNames.value[0] || 'institucional'
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true

  try {
    const settings = Object.values(groupedSettings.value).flat().map(setting => ({
      key: setting.key,
      value: setting.value
    }))

    const data = await apiFetch('/settings', {
      method: 'PUT',
      body: JSON.stringify({ settings })
    })

    groupedSettings.value = data.groups || {}
    $q.notify({ type: 'positive', message: 'Configuracoes salvas.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error.message })
  } finally {
    saving.value = false
  }
}

function groupLabel(group) {
  return {
    geral: 'Geral',
    institucional: 'Institucional',
    notificacoes: 'Notificacoes',
    integracoes: 'Integracoes',
    whatsapp: 'WhatsApp',
    sla: 'SLA',
    chamados: 'Chamados',
    automacoes: 'Automacoes',
    avaliacao: 'Avaliacao',
    seguranca: 'Seguranca'
  }[group] || group
}

function groupIcon(group) {
  return {
    geral: 'tune',
    institucional: 'account_balance',
    notificacoes: 'notifications',
    integracoes: 'hub',
    whatsapp: 'chat',
    sla: 'timer',
    chamados: 'support_agent',
    automacoes: 'bolt',
    avaliacao: 'star_rate',
    seguranca: 'security'
  }[group] || 'settings'
}
</script>
