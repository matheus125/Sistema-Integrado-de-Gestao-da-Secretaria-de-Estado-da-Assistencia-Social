import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'
import { formatDate, formatDateTime } from '../src/composables/useDateFormat.js'

test('formata data ISO no padrao brasileiro sem alterar o dia', () => {
  assert.equal(formatDate('2026-11-22'), '22/11/2026')
  assert.equal(formatDate('2024-02-29T23:59:00'), '29/02/2024')
})

test('formata data e hora local sem conversao de fuso implicita', () => {
  assert.equal(formatDateTime('2026-11-22T08:05'), '22/11/2026 08:05')
  assert.equal(formatDateTime('2026-11-22 18:45:59'), '22/11/2026 18:45')
})

test('converte timestamp com fuso de forma deterministica quando solicitado', () => {
  assert.equal(
    formatDateTime('2026-11-22T12:30:00.000000Z', { timeZone: 'America/Manaus' }),
    '22/11/2026 08:30'
  )
  assert.equal(
    formatDateTime('2026-11-22T12:30:00+00:00', { timeZone: 'UTC' }),
    '22/11/2026 12:30'
  )
})

test('retorna marcador seguro para valores ausentes ou invalidos', () => {
  assert.equal(formatDate(null), '-')
  assert.equal(formatDate('2026-02-30'), '-')
  assert.equal(formatDateTime('texto'), '-')
  assert.equal(formatDateTime('2026-11-22T25:00'), '-')
})

test('tabelas e relatorios usam o helper central', async () => {
  const files = await Promise.all([
    '../src/pages/MaterialsPage.vue',
    '../src/pages/TransportPage.vue',
    '../src/pages/ItHelpDeskPage.vue',
    '../src/pages/ItPreventiveMaintenancePage.vue',
    '../src/pages/ReportsPage.vue',
    '../src/layouts/MainLayout.vue'
  ].map(file => readFile(new URL(file, import.meta.url), 'utf8')))

  files.forEach(source => assert.match(source, /formatDate(?:Time)?/))
  assert.match(files[4], /formatReportValue/)
  assert.doesNotMatch(files[5], /formatSessionDate/)
})

test('inputs preservam valores ISO enviados para a API', async () => {
  const files = await Promise.all([
    '../src/pages/MaterialsPage.vue',
    '../src/pages/TransportPage.vue',
    '../src/pages/ItHelpDeskPage.vue',
    '../src/pages/ReportsPage.vue'
  ].map(file => readFile(new URL(file, import.meta.url), 'utf8')))
  const source = files.join('\n')

  assert.match(source, /v-model="[^\"]+"[^>]+type="date"/)
  assert.match(source, /v-model="assetMovementForm\.moved_at"[^>]+type="datetime-local"/)
  assert.doesNotMatch(source, /v-model="[^\"]+"[^>]+mask="##\/##\/####"/)
})
