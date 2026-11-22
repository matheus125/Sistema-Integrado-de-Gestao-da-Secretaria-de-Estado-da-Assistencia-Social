# 01 — Inventário funcional

## Projeto e ambientes

Caminho auditado: `/home/matheus-mota/Desenvolvimento/Sistema-Integrado-de-Gestao-da-Secretaria-de-Estado-da-Assistencia-Social.`. O ponto final faz parte do nome real da pasta.

Arquivos e diretórios confirmados: `artisan`, `composer.json`, `package.json`, `app/`, `routes/`, `database/`, `tests/` e `frontend/quasar/`.

| Ambiente | Evidência | Banco |
|---|---|---|
| Desenvolvimento atual | `.env`, `APP_ENV=local`, backend 8000, frontend 9000 | MySQL `siges` |
| Testes automatizados | `phpunit.xml` | SQLite `:memory:` |
| Produção planejada | `deploy/production.env.example`, Nginx, PHP-FPM, systemd/supervisor | MySQL |

Comandos confirmados:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm --prefix frontend/quasar run dev -- --hostname 127.0.0.1 --port 9000
php artisan test
npm --prefix frontend/quasar run build
```

## Comparação com o levantamento anterior

| Item | Histórico | Atual | Variação |
|---|---:|---:|---:|
| Rotas API | 115 | 143 | +28 |
| Controllers | 34 | 42 | +8 |
| Models | 32 | 40 | +8 |
| Migrations | 15 | 24 | +9 |
| Testes aprovados | 31 | 84 | +53 |
| Asserções | 185 | 468 | +283 |
| Git | ausente | presente, branch `main` | evoluiu |
| CI/CD | sem evidência | sem workflow encontrado | inalterado |
| Deploy | insuficiente | documentação e artefatos presentes | evoluiu |

## Módulos oficiais e domínios transversais

Há 8 módulos RBAC oficiais em `config/siges.php`: Dashboard, Administração, Transporte, Materiais e Patrimônio, Informática, Relatórios, Auditoria e Configurações.

Domínios transversais adicionais: autenticação, anexos, notificações, busca global e webhook/integrações. Assim, o inventário operacional considera 8 módulos e 5 domínios transversais.

## Estrutura por módulo

| Módulo | Frontend | Backend principal | Modelos/tabelas principais | Testes |
|---|---|---|---|---|
| Dashboard | `IndexPage.vue` | `DashboardController` | agregações dos módulos | `AccessControlTest` |
| Administração | `AdminPage.vue` | `Admin/*Controller` | users, departments, roles/permissions | cobertura específica insuficiente |
| Transporte | `TransportPage.vue` | `Transport/*Controller` | vehicles, drivers, vehicle_requests, maintenances, checklists, histories | 22+ cenários |
| Materiais/Patrimônio | `MaterialsPage.vue` | `Materials/*Controller` | products, stock_movements, assets, asset_movements | escopo de patrimônio; demais lacunas |
| Informática | `ItHelpDeskPage.vue`, `ItPreventiveMaintenancePage.vue`, `ItSlaCalendars.vue` | 18 controllers `It/*` | chamados, SLA, equipes, conhecimento, automações, catálogo, aprovações e manutenção | maior cobertura do projeto |
| Relatórios | `ReportsPage.vue` | `ReportController` | consultas agregadas e exportações | prévias e exportações reais |
| Auditoria | `AuditPage.vue` | `AuditLogController` | activity_log | navegação E2E; detalhe não exercitado |
| Configurações | `SettingsPage.vue` | `SettingController` | system_settings | leitura E2E e update automatizado |
| Transversal | `MainLayout`, auth store/service | Auth, Attachment, Notification, Search, Webhook | tokens, attachments, notifications | cobertura parcial e testes dinâmicos |

## API

| Prefixo | Rotas registradas |
|---|---:|
| `it` | 69 |
| `transport` | 26 |
| `materials` | 14 |
| `admin` | 13 |
| `attachments` | 4 |
| `auth` | 3 |
| `reports` | 3 |
| `audit` | 2 |
| `settings` | 2 |
| `webhooks` | 2 |
| dashboard/modules/notifications/search/user | 5 |
| **Total API** | **143** |

Todas, exceto login e webhook, estão sob `auth:sanctum`; os módulos usam middleware de permissão de visualização e os métodos modificadores verificam permissões específicas nos controllers. Foram identificados 53 padrões literais distintos de URL diretamente exercitados pela suíte, além das chamadas do E2E.

O registro individual dos 143 endpoints, com método, URL, controller, autenticação, autorização, consumidor e evidência de cobertura, está no [apêndice de endpoints](09-apendice-endpoints.md).

## Matriz de funcionalidades

| ID | Módulo | Funcionalidade | Implementação | Teste real | Status | Prioridade |
|---|---|---|---|---|---|---|
| AUT-001 | Autenticação | Login por e-mail/senha | Backend + frontend | API e navegador | FUNCIONANDO | P0 |
| AUT-002 | Autenticação | Logout e revogação do token atual | Backend + frontend | API: token passou a 401 | FUNCIONANDO | P0 |
| AUT-003 | Autenticação | Sessão atual `/auth/me` | Backend + frontend | API/E2E | FUNCIONANDO | P1 |
| AUT-004 | Autenticação | Rate limiting de login | Ausente | 10 tentativas, nenhuma 429 | NÃO IMPLEMENTADA | P0 |
| AUT-005 | Autenticação | Recuperação de senha | Ausente no fluxo/API | Não aplicável | NÃO IMPLEMENTADA | P2 |
| AUT-006 | Autenticação | Expiração/timeout de token | Configuração não aplicada | Inspeção dinâmica/estática | NÃO IMPLEMENTADA | P1 |
| CORE-001 | Core | Catálogo de módulos por permissão | Backend + menu | Teste automatizado | FUNCIONANDO | P1 |
| CORE-002 | Dashboard | Indicadores e alertas por permissão | Full stack | Teste + E2E | FUNCIONANDO | P1 |
| CORE-003 | Core | Busca global | Full stack | Teste automatizado | FUNCIONANDO | P2 |
| CORE-004 | Core | Notificações internas | Full stack | E2E sem erros | FUNCIONANDO | P2 |
| CORE-005 | Core | Anexos privados: upload/lista/download/exclusão | Full stack | Testes de ticket/autorização | FUNCIONANDO | P1 |
| ADM-001 | Administração | Listar/criar usuários | Full stack | Lista E2E e criação isolada | FUNCIONANDO | P1 |
| ADM-002 | Administração | Editar/excluir usuários | Full stack | Não exercitado ponta a ponta | NÃO TESTADA | P1 |
| ADM-003 | Administração | CRUD de setores | Full stack | Apenas listagem E2E | NÃO TESTADA | P2 |
| ADM-004 | Administração | CRUD de perfis/permissões | Full stack | Apenas listagem E2E | NÃO TESTADA | P1 |
| TRA-001 | Transporte | CRUD de veículos | Full stack | Lista E2E e criação isolada | PARCIAL | P1 |
| TRA-002 | Transporte | CRUD de motoristas | Full stack | Não exercitado ponta a ponta | NÃO TESTADA | P1 |
| TRA-003 | Transporte | Criar/listar/editar solicitações | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| TRA-004 | Transporte | Aprovação, cancelamento e transições | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| TRA-005 | Transporte | Checklists de saída/retorno e KM | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| TRA-006 | Transporte | Conflitos de agenda/capacidade/disponibilidade | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| TRA-007 | Transporte | Agenda e resumo operacional | Full stack | Testes + E2E | FUNCIONANDO | P2 |
| TRA-008 | Transporte | Manutenção da frota | Full stack | Testes de ciclo e relatórios | FUNCIONANDO | P1 |
| MAT-001 | Materiais | CRUD de produtos | Full stack | Apenas tela/lista E2E | NÃO TESTADA | P1 |
| MAT-002 | Materiais | Entradas e saídas de estoque | Full stack/transação | Não exercitado ponta a ponta | NÃO TESTADA | P1 |
| MAT-003 | Materiais | Bloqueio de estoque insuficiente | Backend | Não executado | NÃO TESTADA | P1 |
| MAT-004 | Patrimônio | CRUD e escopo por setor | Full stack | Escopo testado; CRUD parcial | PARCIAL | P1 |
| MAT-005 | Patrimônio | Movimentação/transferência/manutenção/baixa | Full stack/transação | Não executado | NÃO TESTADA | P1 |
| IT-001 | Informática | Abrir/listar/detalhar/editar chamado | Full stack | Testes de workflow + E2E | FUNCIONANDO | P0 |
| IT-002 | Informática | Meus chamados e escopo do solicitante | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| IT-003 | Informática | Filtros, busca, paginação e Kanban | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| IT-004 | Informática | Categorias hierárquicas | Full stack | CRUD/uso testados | FUNCIONANDO | P1 |
| IT-005 | Informática | Tipos de chamado | Full stack | Validação/filtro/relatório testados | FUNCIONANDO | P1 |
| IT-006 | Informática | Fluxo de status e reabertura | Full stack | Testes automatizados | FUNCIONANDO | P0 |
| IT-007 | Informática | Cancelamento, timeline e notas internas | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| IT-008 | Informática | Mensagens públicas/internas | Full stack | Testes de direção/visibilidade | FUNCIONANDO | P1 |
| IT-009 | Informática | Anexos do chamado | Full stack | Upload/escopo/exclusão testados | FUNCIONANDO | P1 |
| IT-010 | Informática | Regras e calendários de SLA | Full stack | Cobertura extensa | FUNCIONANDO | P0 |
| IT-011 | Informática | Equipes e atribuição | Backend + UI | Testes de equipe/atribuição | FUNCIONANDO | P1 |
| IT-012 | Informática | Escalonamento e notificações | Backend agendado | Testes automatizados | FUNCIONANDO | P1 |
| IT-013 | Informática | Templates de resposta | Full stack | Escopos testados | FUNCIONANDO | P2 |
| IT-014 | Informática | Base de conhecimento/sugestões/publicação | Full stack | Testes automatizados | FUNCIONANDO | P2 |
| IT-015 | Informática | Avaliação de atendimento | Full stack | Testes automatizados | FUNCIONANDO | P2 |
| IT-016 | Informática | Tarefas/subtarefas | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| IT-017 | Informática | Checklists e templates | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| IT-018 | Informática | Relações e duplicidade de chamados | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| IT-019 | Informática | Incidente em massa/reflexo | Backend + UI | Cobertura parcial | PARCIAL | P2 |
| IT-020 | Informática | Causa raiz e solução técnica | Full stack/relatório | Fluxos e relatórios testados parcialmente | PARCIAL | P2 |
| IT-021 | Informática | Catálogo de serviços e aprovação | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| IT-022 | Informática | Impacto/urgência e cálculo de prioridade | Full stack | Testes automatizados | FUNCIONANDO | P1 |
| IT-023 | Informática | Motor de automação | Full stack/service | Teste de mudança de status | FUNCIONANDO | P1 |
| IT-024 | Informática | Manutenção preventiva | Full stack/agendamento | Testes + E2E | FUNCIONANDO | P1 |
| IT-025 | Informática | WhatsApp bidirecional | Backend | Payload anônimo criou chamado | COM ERRO | P0 |
| IT-026 | Informática | Dashboards operacional/técnico/recorrência | Full stack | Operacional/recorrência testados | PARCIAL | P2 |
| IT-027 | Informática | Ações em massa | Backend + UI | Testes automatizados | FUNCIONANDO | P1 |
| IT-028 | Informática | Templates WhatsApp/status de entrega | Backend + UI | Teste automatizado parcial | PARCIAL | P2 |
| IT-029 | Informática | Recomendações de atribuição | Backend | Sem cenário dedicado | NÃO TESTADA | P2 |
| REP-001 | Relatórios | Opções e prévia de 18 relatórios | Full stack | Todos consultados no E2E | FUNCIONANDO | P1 |
| REP-002 | Relatórios | Exportação CSV, XLSX e PDF | Full stack | Downloads reais aprovados | FUNCIONANDO | P1 |
| REP-003 | Relatórios | Escopo e permissões | Backend | Testes automatizados parciais | PARCIAL | P1 |
| AUD-001 | Auditoria | Listagem e filtros | Full stack | Carregamento E2E | FUNCIONANDO | P1 |
| AUD-002 | Auditoria | Detalhe de propriedades | Full stack | Não aberto no E2E | NÃO TESTADA | P2 |
| CFG-001 | Configurações | Listar parâmetros | Full stack | E2E | FUNCIONANDO | P1 |
| CFG-002 | Configurações | Atualizar parâmetros | Full stack | Teste automatizado | FUNCIONANDO | P1 |
| CFG-003 | Configurações | Proteção de segredos | Mascaramento sem criptografia | Análise estática | COM ERRO | P0 |
| DB-001 | Banco | Migrations MySQL | 24 migrations aplicadas | `migrate:status` | FUNCIONANDO | P0 |
| DB-002 | Banco | FKs e integridade referencial | 99 FKs | Zero órfãos detectados | FUNCIONANDO | P0 |
| DB-003 | Banco | Concorrência de estoque/patrimônio | Transação + lock | Sem concorrência real MySQL | PARCIAL | P1 |
| DB-004 | Banco | Compatibilidade SQLite/MySQL | Código/testes | Testes dedicados aprovados | FUNCIONANDO | P1 |
| FE-001 | Frontend | Navegação das rotas | Vue Router | 9 rotas autenticadas + login | FUNCIONANDO | P1 |
| FE-002 | Frontend | Responsividade 375/768/1366 | CSS/Quasar | Sem overflow nas 9 rotas | FUNCIONANDO | P2 |
| FE-003 | Frontend | Validação obrigatória dos formulários | QForm + API | Amostra UI + backend | PARCIAL | P1 |
| FE-004 | Frontend | Máscaras brasileiras | Ausentes | Inspeção + API inválida aceita | COM ERRO | P1 |
| FE-005 | Frontend | Acessibilidade de controles | Parcial | Botões sem nome acessível | COM ERRO | P2 |
| FE-006 | Frontend | Tratamento de erro/loading/estado vazio | Implementado | Smoke e inspeção | PARCIAL | P2 |
| FE-007 | Frontend | Testes automatizados unitários/E2E no projeto | Ausentes | Inventário | NÃO IMPLEMENTADA | P1 |
| OPS-001 | Operação | Build SPA | Quasar/Vite | Build aprovado | FUNCIONANDO | P0 |
| OPS-002 | Operação | Queue e scheduler | Config/systemd/supervisor | Não validado no host alvo | PARCIAL | P1 |
| OPS-003 | Operação | Backup e restore | Scripts/systemd | Restore não autorizado | BLOQUEADA | P0 |
| OPS-004 | Operação | HTTPS/Nginx/PHP-FPM | Templates de deploy | Host alvo indisponível | BLOQUEADA | P0 |
| OPS-005 | Operação | Health check | Laravel `/up` | HTTP 200 | FUNCIONANDO | P1 |
| OPS-006 | Operação | CI/CD | Ausente | Inventário | NÃO IMPLEMENTADA | P1 |
| SEC-001 | Segurança | Auditoria de dependências | Ferramentas executadas | Avisos críticos/altos | COM ERRO | P0 |

Atualização de 22/11/2026: a condição registrada em FE-005 foi corrigida pela
FIX-018 no código-fonte. O inventário automatizado passou de 96 controles sem
nome explícito para 166/166 botões com ícone ou formato redondo nomeados. A
classificação histórica da tabela foi preservada; a auditoria do DOM renderizado
com axe permanece planejada para a FIX-021.

Atualização de 13/11/2026: a condição original de OPS-003 foi superada pela
FIX-008 em MySQL descartável. Backup, restore, 59 tabelas, 99 foreign keys, 26
migrations e contagens/hashes foram validados; restore periódico/off-site segue
pendente na FIX-034.

## Resumo quantitativo da matriz

Os totais consolidados e percentuais por módulo estão no [resumo executivo](08-resumo-executivo.md). A cobertura funcional não equivale a taxa de aprovação: itens apenas inspecionados permanecem como NÃO TESTADA ou PARCIAL.
