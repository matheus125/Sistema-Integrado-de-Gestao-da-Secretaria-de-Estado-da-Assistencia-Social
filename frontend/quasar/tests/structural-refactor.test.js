import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'

const controller = readFileSync(new URL('../../../app/Http/Controllers/Api/Reports/ReportController.php', import.meta.url), 'utf8')
const builder = readFileSync(new URL('../../../app/Services/Reports/ReportBuilder.php', import.meta.url), 'utf8')
const generator = readFileSync(new URL('../../../app/Services/Reports/ReportExportGenerator.php', import.meta.url), 'utf8')
const helpDeskPage = readFileSync(new URL('../src/pages/ItHelpDeskPage.vue', import.meta.url), 'utf8')
const kanban = readFileSync(new URL('../src/components/ItKanbanBoard.vue', import.meta.url), 'utf8')

test('controller de relatórios delega consultas ao serviço focado', () => {
  assert.match(controller, /private readonly ReportBuilder \$reports/)
  assert.match(controller, /\$this->reports->build\(\$request\)/)
  assert.match(controller, /\$this->reports->canView\(/)
  assert.doesNotMatch(controller, /DB::table\(/)
  assert.ok(controller.split('\n').length < 180)
  assert.match(builder, /public const REPORTS/)
  assert.match(builder, /public function buildForExport\(/)
  assert.match(builder, /private function itTickets\(/)
})

test('gerador de arquivo depende do ReportBuilder e não da camada HTTP', () => {
  assert.match(generator, /app\(ReportBuilder::class\)->buildForExport/)
  assert.doesNotMatch(generator, /ReportController/)
})

test('página do Help Desk delega renderização e drag-and-drop ao componente Kanban', () => {
  assert.match(helpDeskPage, /import ItKanbanBoard from/)
  assert.match(helpDeskPage, /<it-kanban-board/)
  assert.match(helpDeskPage, /@move-ticket="moveKanbanTicket"/)
  assert.doesNotMatch(helpDeskPage, /class="kanban-board"/)
  assert.doesNotMatch(helpDeskPage, /function dragTicket\(/)
  assert.match(kanban, /const columns = computed\(/)
  assert.match(kanban, /function dragTicket\(/)
  assert.match(kanban, /emit\('move-ticket', \{ ticket, status \}\)/)
})
