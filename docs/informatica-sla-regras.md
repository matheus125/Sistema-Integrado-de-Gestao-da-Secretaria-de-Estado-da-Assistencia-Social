# SLA configurável — Parte 3

As regras de SLA são administradas na aba **SLA** do Help Desk. A leitura exige `informatica.visualizar`; criação e alteração exigem `informatica.editar`.

Uma regra pode combinar prioridade, categoria, tipo e setor. Quanto mais campos de escopo coincidirem, maior a especificidade. Empates são resolvidos por `rule_priority` e, depois, pelo identificador mais recente. Regras sem escopo funcionam como regra geral.

Uma regra fallback não possui escopo e só é consultada quando nenhuma regra geral ou específica ativa corresponde. O backend rejeita fallback com prioridade, categoria, tipo ou setor para evitar seleção ambígua. Calendários inativos nunca são usados por regras ativas.

Cada regra configura os minutos de primeira resposta, início do atendimento e resolução. Quando há calendário, os três prazos usam o expediente, intervalos, feriados e exceções configurados na Parte 4. Sem calendário, o prazo permanece em minutos corridos para compatibilidade com regras legadas.

Ao criar ou editar um chamado, o `SlaService` resolve a regra e grava `sla_rule_id`, `first_response_due_at`, `start_due_at` e `sla_due_at`. Esses prazos são preservados depois de gravados; alterar uma regra ou calendário não recalcula chamados existentes automaticamente.

## API

- `GET /api/it/sla-rules`: lista regras com calendário.
- `POST /api/it/sla-rules`: cria regra.
- `PUT /api/it/sla-rules/{id}`: altera regra, inclusive parcialmente.

## Validação

`tests/Feature/ItHelpDeskWorkflowTest.php` cobre CRUD, permissões, escolha da regra mais específica, fallback e rejeição de fallback com escopo. `tests/Feature/ItSlaCalendarTest.php` cobre a integração com calendários e o cálculo dos três prazos na criação real do chamado.
