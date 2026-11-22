<template>
  <q-card flat bordered class="q-mb-lg">
    <q-card-section class="row items-center justify-between">
      <div><div class="text-h6">Calendários de atendimento</div><div class="text-caption">Expediente, intervalos, feriados e exceções do SLA.</div></div>
      <q-btn color="primary" label="Novo calendário" @click="open()" />
    </q-card-section>
    <q-list separator>
      <q-item v-for="calendar in calendars" :key="calendar.id">
        <q-item-section>
          <q-item-label>{{ calendar.name }} <q-badge :color="calendar.active ? 'positive' : 'grey'">{{ calendar.active ? 'Ativo' : 'Inativo' }}</q-badge></q-item-label>
          <q-item-label caption>{{ time(calendar.starts_at) }} – {{ time(calendar.ends_at) }} · {{ calendar.count_weekends ? 'Todos os dias' : days.filter(day => (calendar.weekdays || []).includes(day.value)).map(day => day.label).join(', ') }}</q-item-label>
        </q-item-section>
        <q-item-section side><q-btn flat label="Editar" @click="open(calendar)" /></q-item-section>
      </q-item>
      <q-item v-if="!calendars.length"><q-item-section>Nenhum calendário cadastrado.</q-item-section></q-item>
    </q-list>
  </q-card>
  <q-dialog v-model="dialog" persistent @before-show="clearServerValidationErrors">
    <q-card style="width: 760px; max-width: 95vw">
      <q-form @submit="save">
        <q-card-section class="text-h6">{{ form.id ? 'Editar' : 'Novo' }} calendário</q-card-section>
        <q-card-section class="q-gutter-md">
          <q-banner class="bg-grey-2">Horários no fuso do sistema: {{ timezone }}. As alterações valem para novos cálculos; os prazos já gravados são preservados.</q-banner>
          <q-input v-model="form.name" v-bind="serverErrorProps('name')" outlined label="Nome" :maxlength="160" :rules="[required, maxLength(160)]" />
          <q-field borderless v-bind="serverErrorProps('active')"><template #control><q-toggle v-model="form.active" label="Ativo" /></template></q-field>
          <q-field borderless v-bind="serverErrorProps('count_weekends')"><template #control><q-toggle v-model="form.count_weekends" label="Atender todos os dias da semana" /></template></q-field>
          <q-field v-if="!form.count_weekends" borderless v-bind="serverErrorProps('weekdays')"><template #control><q-option-group v-model="form.weekdays" :options="days" type="checkbox" inline /></template></q-field>
          <div class="row q-col-gutter-md">
            <div v-for="field in timeFields" :key="field.key" class="col-12 col-sm-6">
              <q-input v-model="form[field.key]" v-bind="serverErrorProps(field.key)" outlined type="time" :label="field.label" :clearable="field.optional" :rules="field.optional ? [] : [required]" />
            </div>
          </div>
          <div class="text-subtitle1">Feriados</div>
          <div v-for="(_, index) in form.holidays" :key="index" class="row items-start q-gutter-sm">
            <q-input v-model="form.holidays[index]" v-bind="serverErrorProps(`holidays.${index}`)" outlined type="date" label="Data" :rules="[required]" />
            <q-btn flat label="Remover" @click="form.holidays.splice(index, 1)" />
          </div>
          <q-btn outline label="Adicionar feriado" @click="form.holidays.push('')" />
          <div class="text-subtitle1">Exceções</div>
          <p>Exceções prevalecem sobre feriados e dias da semana. Deixe os horários vazios para usar o expediente habitual. Ao informar um horário especial, informe início e fim; o intervalo será o definido nessa exceção.</p>
          <q-card v-for="(exception, index) in form.exceptions" :key="index" flat bordered>
            <q-card-section class="q-gutter-sm">
              <q-input v-model="exception.date" v-bind="serverErrorProps(`exceptions.${index}.date`)" outlined type="date" label="Data" :rules="[required]" />
              <q-field borderless v-bind="serverErrorProps(`exceptions.${index}.working`)"><template #control><q-toggle v-model="exception.working" label="Haverá atendimento" /></template></q-field>
              <div v-if="exception.working" class="row q-col-gutter-md">
                <div v-for="field in timeFields" :key="field.key" class="col-12 col-sm-6">
                  <q-input v-model="exception[field.key]" v-bind="serverErrorProps(`exceptions.${index}.${field.key}`)" outlined type="time" clearable :label="field.label" />
                </div>
              </div>
              <q-btn flat label="Remover exceção" @click="form.exceptions.splice(index, 1)" />
            </q-card-section>
          </q-card>
          <q-btn outline label="Adicionar exceção" @click="form.exceptions.push({ date: '', working: false })" />
          <q-banner v-if="error" class="bg-red-1 text-negative" role="alert">{{ error }}</q-banner>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" :disable="saving" @click="dialog = false" />
          <q-btn type="submit" color="primary" label="Salvar" :loading="saving" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref } from 'vue'
import { useQuasar } from 'quasar'
import { apiFetch } from '@/services/api'
import { clearServerValidationErrors, serverErrorProps } from '@/services/server-validation'
import { maxLength } from '@/utils/form-validation'

defineProps({ calendars: { type: Array, default: () => [] }, timezone: { type: String, default: 'UTC' } })
const emit = defineEmits(['saved'])
const $q = useQuasar()
const dialog = ref(false)
const saving = ref(false)
const error = ref('')
const form = ref({})
const required = value => Boolean(value) || 'Campo obrigatório'
const time = value => String(value || '').slice(0, 5)
const days = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'].map((label, index) => ({ label, value: index + 1 }))
const timeFields = [
  { key: 'starts_at', label: 'Início do expediente' },
  { key: 'ends_at', label: 'Fim do expediente' },
  { key: 'break_starts_at', label: 'Início do intervalo', optional: true },
  { key: 'break_ends_at', label: 'Fim do intervalo', optional: true }
]
function open(calendar) {
  const value = calendar ? JSON.parse(JSON.stringify(calendar)) : {
    name: '', weekdays: [1, 2, 3, 4, 5], starts_at: '08:00', ends_at: '17:00',
    break_starts_at: null, break_ends_at: null, count_weekends: false, holidays: [], exceptions: [], active: true
  }
  value.holidays ||= []
  value.exceptions ||= []
  for (const field of timeFields) value[field.key] = time(value[field.key]) || null
  form.value = value
  error.value = ''
  dialog.value = true
}
async function save() {
  if (saving.value) return
  saving.value = true
  error.value = ''
  try {
    const { id, name, weekdays, count_weekends, holidays, exceptions, active } = form.value
    const body = { name, weekdays, count_weekends, holidays, exceptions, active }
    for (const field of timeFields) body[field.key] = form.value[field.key] || null
    await apiFetch(id ? `/it/sla-calendars/${id}` : '/it/sla-calendars', { method: id ? 'PUT' : 'POST', body: JSON.stringify(body) })
    dialog.value = false
    $q.notify({ type: 'positive', message: 'Calendário salvo.' })
    emit('saved')
  } catch (failure) {
    error.value = failure.message
  } finally {
    saving.value = false
  }
}
</script>
