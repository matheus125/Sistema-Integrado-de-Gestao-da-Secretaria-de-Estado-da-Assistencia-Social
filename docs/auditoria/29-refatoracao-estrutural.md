# 29 — Refatoração estrutural incremental

Data da validação: 23/11/2026.

## Escopo

A FIX-025 reduziu duas concentrações de responsabilidade identificadas na
auditoria: a construção de relatórios dentro do controller HTTP e a
renderização/coordenação do Kanban dentro de `ItHelpDeskPage.vue`. Rotas,
payloads, regras de autorização, filtros, transições de chamado e schema do
banco foram preservados.

## Diagnóstico

- `ReportController.php` possuía 591 linhas e reunia validação HTTP,
  autorização, consultas de 18 relatórios e gerenciamento de exportações;
- `ReportExportGenerator` reutilizava essas consultas instanciando um
  controller, acoplando o processamento em fila à camada HTTP;
- `ItHelpDeskPage.vue` possuía 2.314 linhas e também concentrava agrupamento,
  apresentação e drag-and-drop do quadro Kanban.

## Correção aplicada

### Backend

- criado `ReportBuilder`, serviço dedicado às consultas, limites e autorização
  dos relatórios;
- `ReportController` passou a cuidar somente do contrato HTTP, validação,
  prévia e ciclo das exportações;
- `ReportExportGenerator` passou a depender diretamente de `ReportBuilder`;
- o controller foi reduzido de 591 para 123 linhas, sem alterar os endpoints;
- a regra de construção continua compartilhada entre prévia e exportação, sem
  duplicação de consultas.

### Frontend

- criado `ItKanbanBoard.vue`, responsável pelas colunas, cartões e eventos de
  drag-and-drop;
- `ItHelpDeskPage.vue` permanece responsável pelos filtros, paginação,
  chamadas à API e confirmação das transições;
- a página foi reduzida de 2.314 para 2.245 linhas nesta etapa;
- o componente extraído possui 137 linhas e comunica ações por eventos, sem
  assumir acesso direto ao serviço HTTP.

## Testes

Foram adicionados três testes estruturais permanentes para confirmar que:

1. o controller de relatórios delega as consultas e permanece sem acesso
   direto ao banco;
2. o gerador de exportação depende do serviço e não do controller;
3. a página do Help Desk delega a renderização e o drag-and-drop ao componente
   Kanban.

Também foram executados os testes funcionais existentes de relatórios,
exportação e Help Desk.

## Resultados

| Verificação | Resultado |
|---|---:|
| Testes direcionados Laravel | 12 aprovados, 154 asserções |
| Regressão Laravel | 177 testes, 1.142 asserções |
| Regressão frontend | 40 testes, 0 falhas |
| Pint nos arquivos PHP refatorados | aprovado |
| Build Quasar SPA | aprovado; 39 arquivos JS, 669,74 KB |

A suíte Laravel utilizou SQLite isolado. A refatoração não possui migration e
não modificou dados ou schema do MySQL real.

## Riscos residuais

- `ItHelpDeskPage.vue` ainda possui 2.245 linhas porque agrega nove fluxos de
  formulário; novas extrações devem continuar de forma incremental e coberta
  por testes;
- `ReportBuilder` possui 482 linhas e poderá ser dividido por domínio quando
  houver necessidade de evolução independente dos relatórios;
- `ItTicketController` e `TransportPage.vue` permanecem grandes e não fizeram
  parte desta correção;
- os testes estruturais protegem as fronteiras extraídas, mas testes de
  componentes com Vitest e E2E permanente continuam reservados às FIX-026 e
  FIX-027.

## Conclusão

A FIX-025 está **VALIDADA**. As duas fronteiras foram extraídas sem mudança
funcional, e as regressões backend, frontend e de build permaneceram
aprovadas. A FIX-026 foi concluída posteriormente e está documentada em
`30-vitest-componentes.md`.
