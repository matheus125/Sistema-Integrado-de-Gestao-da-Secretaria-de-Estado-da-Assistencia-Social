import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'

const controller = readFileSync('../../app/Http/Controllers/Api/It/ItTicketController.php', 'utf8')
const page = readFileSync('src/pages/ItHelpDeskPage.vue', 'utf8')

test('API do Kanban pagina a consulta e limita o tamanho por requisição', () => {
  const block = controller.match(/if \(\$request->boolean\('kanban'\)\) \{([\s\S]*?)\n        \}/)?.[1] || ''

  assert.match(block, /->paginate\(\$filters\['per_page'\] \?\? 25\)/)
  assert.doesNotMatch(block, /->get\(\)/)
  assert.match(controller, /'per_page' => \['nullable', 'integer', 'min:1', 'max:'\.config\('pagination\.max_per_page', 100\)\]/)
})

test('resposta do Kanban publica metadados e links de paginação', () => {
  for (const field of ['current_page', 'last_page', 'per_page', 'total', 'from', 'to']) {
    assert.match(controller, new RegExp(`'${field}' => \\$paginator->`))
  }
  assert.match(controller, /'prev' => \$paginator->previousPageUrl\(\)/)
  assert.match(controller, /'next' => \$paginator->nextPageUrl\(\)/)
})

test('frontend mantém paginação própria e não acumula páginas do Kanban', () => {
  assert.match(page, /const kanbanPagination = ref\(/)
  assert.match(page, /<q-pagination/)
  assert.match(page, /Cartões por página/)
  assert.match(page, /tickets\.value = ticketData\.data \|\| \[\]/)
  assert.doesNotMatch(page, /tickets\.value\.push\(/)
  assert.match(page, /async function changeKanbanPage\(/)
  assert.match(page, /async function changeKanbanPageSize\(/)
})
