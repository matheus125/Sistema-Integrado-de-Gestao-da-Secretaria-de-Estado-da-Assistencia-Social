# 07 — Plano de correções

Nenhuma correção abaixo foi aplicada durante a auditoria.

## Etapa 1 — Segurança crítica

| ID | Tarefa | Módulo | Prioridade | Dependências/arquivos | Critério de aceite | Testes |
|---|---|---|---|---|---|---|
| FIX-001 | Validar assinatura `X-Hub-Signature-256` antes de processar webhook | Informática | P0 | App Secret; controller webhook | payload sem assinatura/assinatura inválida recebe 401/403 e não cria/atualiza nada | feature test + replay/idempotência |
| FIX-002 | Atualizar dependências críticas/altas | Core | P0 | composer/npm lockfiles | audits sem crítico e riscos altos justificados/corrigidos | 84 testes, build, E2E, exports |
| FIX-003 | Aplicar rate limit ao login | Auth | P0 | routes/bootstrap/provider | excesso recebe 429 sem enumerar conta | testes IP+email e reset de janela |
| FIX-004 | Criptografar segredos e rotacioná-los | Configurações | P0 | SystemSetting, migration segura/command | valor não legível no banco/backup e UI continua mascarando | round-trip, key rotation, backup |
| FIX-005 | Remover token padrão do webhook | Informática | P0 | seeder/readiness | produção recusa valor default/vazio | production-check |

## Etapa 2 — Bugs que impedem operações

| ID | Tarefa | Módulo | Prioridade | Critério de aceite |
|---|---|---|---|---|
| FIX-006 | Definir expiração, inatividade e gestão de tokens | Auth | P1 | token expirado/inativo recebe 401; setting é efetivo |
| FIX-007 | Criar suíte CRUD de Administração e Materiais | QA | P1 | create/read/update/delete e permissões por perfil aprovados |
| FIX-008 | Testar restore em MySQL descartável | Operação | P1 | backup restaurado, migrations e checks íntegros |

## Etapa 3 — Integridade e validações

| ID | Tarefa | Módulo | Prioridade | Critério de aceite |
|---|---|---|---|---|
| FIX-009 | Validar/normalizar telefone | Administração/IT/Transporte | P1 | letras rejeitadas; fixo/celular válidos persistem normalizados |
| FIX-010 | Validar placas antiga e Mercosul | Transporte | P1 | `x` rejeitada; formatos válidos aceitos sem duplicidade por caixa/formatação |
| FIX-011 | Definir validação CNH/RENAVAM | Transporte | P2 | regra institucional documentada e coberta |
| FIX-012 | Aplicar limites de texto também no frontend e completar backend | Geral | P2 | limites coerentes e erros de campo claros |
| FIX-013 | Testar concorrência em MySQL | Estoque/Transporte | P1 | sem saldo negativo, dupla agenda ou lost update |

## Etapa 4 — Máscaras e padronização

| ID | Tarefa | Prioridade | Critério de aceite |
|---|---|---|---|
| FIX-014 | Componente compartilhado de telefone | P2 | digitar/colar/apagar/editar/mobile aprovados; payload normalizado |
| FIX-015 | Componente de placa | P2 | ambos formatos, cursor e edição aprovados |
| FIX-016 | Componente de moeda BRL | P2 | `R$ 1.234,56` converte corretamente para decimal |
| FIX-017 | Padronizar datas/horas exibidas | P2 | exibição pt-BR e payload ISO consistente |

## Etapa 5 — UX, responsividade e acessibilidade

| ID | Tarefa | Prioridade | Critério de aceite |
|---|---|---|---|
| FIX-018 | Nome acessível em todos os botões icon-only | P2 | zero botão sem accessible name em axe/Playwright |
| FIX-019 | Mapear 422 por campo | P2 | todos os erros backend aparecem no campo correto |
| FIX-020 | Testar todos os modais em 375/768 px | P2 | sem corte/overflow; foco preso e restaurado |
| FIX-021 | Executar axe e corrigir contraste/foco/teclado | P2 | zero violação crítica/séria acordada |

## Etapa 6 — Performance

| ID | Tarefa | Prioridade | Critério de aceite |
|---|---|---|---|
| FIX-022 | Paginar/virtualizar Kanban | P1 | volume grande sem carregar tudo em memória |
| FIX-023 | Exportações assíncronas e com limite | P1 | job/stream, timeout e autorização testados |
| FIX-024 | Impor máximo global de `per_page` | P2 | valores extremos limitados/422 |
| FIX-025 | Dividir páginas/controllers monolíticos | P2 | componentes/serviços menores com testes, sem mudança funcional |

## Etapa 7 — Testes automatizados

| ID | Tarefa | Prioridade | Critério de aceite |
|---|---|---|---|
| FIX-026 | Adicionar Vitest/component tests | P1 | formulários, stores e serviço API cobertos |
| FIX-027 | Incorporar Playwright ao projeto | P1 | login, nove rotas, CRUD principal, 403/422 e responsividade em CI |
| FIX-028 | Ampliar IDOR por endpoint | P1 | acesso cruzado bloqueado em anexos, relatórios, tickets, ativos e histórico |
| FIX-029 | Testar webhook assinado | P0 | assinatura válida passa; inválida/replay falham |

## Etapa 8 — Produção

| ID | Tarefa | Prioridade | Critério de aceite |
|---|---|---|---|
| FIX-030 | Criar pipeline CI/CD | P1 | lint, audits, PHP tests, build e E2E obrigatórios |
| FIX-031 | Endurecer Nginx/headers | P1 | CSP, HSTS, frame-ancestors, TLS e upload limits revisados |
| FIX-032 | Provisionar `.env` seguro e usuário DB dedicado | P0 | production-check 100% OK |
| FIX-033 | Monitoramento e alertas | P1 | uptime, filas, scheduler, erros e capacidade monitorados |
| FIX-034 | Backup off-site e restore periódico | P0 | RPO/RTO definidos e restore documentado/aprovado |

## Próximas 10 ações

1. FIX-001 — assinatura do webhook.
2. FIX-002 — dependências críticas/altas.
3. FIX-003 — rate limit no login.
4. FIX-004/FIX-005 — criptografia e rotação de segredos.
5. FIX-032 — ambiente de produção seguro.
6. FIX-006 — expiração/inatividade de tokens.
7. FIX-007/FIX-028 — cobertura CRUD/IDOR faltante.
8. FIX-009/FIX-010 — telefone e placa.
9. FIX-022/FIX-023 — Kanban e exportações.
10. FIX-030/FIX-034 — CI e restore comprovado.
