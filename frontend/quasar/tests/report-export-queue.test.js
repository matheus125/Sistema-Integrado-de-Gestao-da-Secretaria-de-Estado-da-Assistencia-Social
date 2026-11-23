import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'

const page = readFileSync(new URL('../src/pages/ReportsPage.vue', import.meta.url), 'utf8')
const controller = readFileSync(new URL('../../../app/Http/Controllers/Api/Reports/ReportController.php', import.meta.url), 'utf8')
const job = readFileSync(new URL('../../../app/Jobs/GenerateReportExport.php', import.meta.url), 'utf8')

test('frontend solicita exportação assíncrona e aguarda conclusão', () => {
  assert.match(page, /apiFetch\('\/reports\/exports',[\s\S]*method: 'POST'/)
  assert.match(page, /waitForExport\(exportRequest\.id\)/)
  assert.match(page, /apiFetch\(`\/reports\/exports\/\$\{exportId\}`\)/)
  assert.match(page, /apiDownload\(completedExport\.download_url/)
  assert.doesNotMatch(page, /apiDownload\(`\/reports\/export\?/)
})

test('API cria exportação pendente e protege status e download pelo proprietário', () => {
  assert.match(controller, /ReportExport::create\(/)
  assert.match(controller, /GenerateReportExport::dispatch\(\$export\)/)
  assert.match(controller, /authorizeExportOwner\(\$request, \$reportExport\)/)
  assert.match(controller, /STATUS_COMPLETED, 409/)
  assert.match(controller, /isPast\(\), 410/)
})

test('job possui timeout, fila dedicada e falha segura', () => {
  assert.match(job, /public int \$tries = 1/)
  assert.match(job, /public bool \$failOnTimeout = true/)
  assert.match(job, /reports\.exports\.timeout/)
  assert.match(job, /reports\.exports\.queue/)
  assert.match(job, /STATUS_FAILED/)
})
