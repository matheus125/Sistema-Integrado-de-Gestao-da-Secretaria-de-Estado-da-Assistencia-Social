<template>
  <div class="kanban-board">
    <section
      v-for="column in columns"
      :key="column.value"
      class="kanban-column"
      :class="{ 'kanban-column--drop-target': canDropInto(column.value) }"
      @dragover.prevent
      @drop="dropTicket(column.value)"
    >
      <div class="kanban-column-header">
        <q-icon :name="statusIcon(column.value)" :color="statusColor(column.value)" />
        <strong>{{ column.label }}</strong>
        <q-badge outline :aria-label="`${column.rows.length} chamado(s) nesta página`">{{ column.rows.length }}</q-badge>
      </div>
      <q-card
        v-for="ticket in column.rows"
        :key="ticket.id"
        flat
        bordered
        :draggable="canEdit"
        class="kanban-card"
        :aria-label="`Chamado #${ticket.id}, ${statusLabel(ticket.status)}. Arraste para mudar o status.`"
        :aria-grabbed="draggedTicket?.id === ticket.id ? 'true' : 'false'"
        :aria-disabled="canEdit ? 'false' : 'true'"
        @dragstart="dragTicket(ticket, $event)"
        @dragend="draggedTicket = null"
        @click="emit('open-ticket', ticket)"
      >
        <q-card-section>
          <div class="row items-center justify-between no-wrap">
            <strong>#{{ ticket.id }}</strong>
            <q-badge :color="slaColor(ticket.sla_due_at, ticket.status)" outline>{{ isOverdue(ticket) ? 'atrasado' : 'SLA' }}</q-badge>
          </div>
          <div class="kanban-title">{{ ticket.description }}</div>
          <div class="kanban-meta">{{ ticket.requester_name }} - {{ ticket.department?.name || ticket.department_name || '-' }}</div>
          <div class="row q-gutter-xs q-mt-sm">
            <q-badge :color="priorityColor(ticket.priority)" outline>{{ labelFor(ticket.priority) }}</q-badge>
            <q-badge :color="typeMeta(ticket.type).color" outline>{{ typeMeta(ticket.type).label }}</q-badge>
            <q-badge outline>{{ ticket.category_model?.name || ticket.category || 'sem categoria' }}</q-badge>
          </div>
          <div class="kanban-meta q-mt-sm">
            {{ ticket.assignee?.name || 'Sem tecnico' }} - aberto ha {{ openTime(ticket.created_at) }}
          </div>
        </q-card-section>
      </q-card>
      <div v-if="!column.rows.length" class="kanban-empty">Nenhum chamado</div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  tickets: { type: Array, default: () => [] },
  statuses: { type: Array, required: true },
  types: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(['open-ticket', 'move-ticket'])
const draggedTicket = ref(null)
const columns = computed(() => props.statuses.map(status => ({
  ...status,
  rows: props.tickets.filter(ticket => ticket.status === status.value)
})))

function canDropInto(status) {
  return props.canEdit && draggedTicket.value && draggedTicket.value.status !== status
    && (draggedTicket.value.available_transitions || []).includes(status)
}

function dragTicket(ticket, event) {
  if (props.loading || !props.canEdit) return
  event?.dataTransfer?.setData('text/plain', String(ticket.id))
  if (event?.dataTransfer) event.dataTransfer.effectAllowed = 'move'
  draggedTicket.value = ticket
}

function dropTicket(status) {
  const ticket = draggedTicket.value
  draggedTicket.value = null
  if (props.loading || !props.canEdit || !ticket || ticket.status === status) return
  emit('move-ticket', { ticket, status })
}

function statusLabel(status) {
  return labelFor(status).replaceAll('_', ' ')
}

function labelFor(value) {
  return String(value || '').replaceAll('_', ' ')
}

function statusColor(status) {
  return {
    aberto: 'primary', triagem: 'warning', em_atendimento: 'info', aguardando_usuario: 'orange',
    resolvido: 'positive', encerrado: 'secondary', cancelado: 'negative'
  }[status] || 'grey'
}

function statusIcon(status) {
  return {
    aberto: 'forum', triagem: 'manage_search', em_atendimento: 'support_agent', aguardando_usuario: 'notifications',
    resolvido: 'task_alt', encerrado: 'check_circle', cancelado: 'cancel'
  }[status] || 'sync_alt'
}

function priorityColor(priority) {
  return { baixa: 'secondary', media: 'primary', alta: 'warning', urgente: 'negative' }[priority] || 'grey'
}

function typeMeta(type) {
  return props.types.find(item => item.value === type) || { label: labelFor(type || 'incidente'), color: 'primary' }
}

function slaColor(value, status) {
  if (['resolvido', 'encerrado', 'cancelado'].includes(status)) return 'positive'
  if (!value) return 'grey'
  return new Date(value) < new Date() ? 'negative' : 'warning'
}

function isOverdue(ticket) {
  return !['resolvido', 'encerrado', 'cancelado'].includes(ticket.status) && ticket.sla_due_at && new Date(ticket.sla_due_at) < new Date()
}

function openTime(value) {
  if (!value) return '-'
  const minutes = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 60000))
  if (minutes < 60) return `${minutes} min`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours} h`
  return `${Math.floor(hours / 24)} d`
}
</script>
