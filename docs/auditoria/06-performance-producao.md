# 06 — Performance e preparação para produção

## Performance

### Evidências positivas

- paginação nas listagens principais;
- eager loading frequente de relações;
- índices e FKs presentes no MySQL;
- build com code splitting por página;
- transações e locks nos pontos de estoque/patrimônio;
- dashboard e relatórios usam agregações no banco.

### Riscos encontrados

| ID | Achado | Impacto | Prioridade |
|---|---|---|---|
| PERF-001 | Kanban retorna toda a coleção filtrada com `get()` | memória/latência com muitos chamados | P1 |
| PERF-002 | Exportações montam todos os registros em memória | OOM/timeout; amplificado por advisories | P1 |
| PERF-003 | `per_page` sem teto em algumas listagens | usuário autenticado pode pedir páginas enormes | P2 |
| PERF-004 | `ItHelpDeskPage.vue` tem 2.220 linhas | manutenção, render e bundle concentrado | P2 |
| PERF-005 | `TransportPage.vue` tem 1.482 linhas e controller 827 | complexidade e regressão | P2 |
| PERF-006 | `ItTicketController` tem 746 linhas; ReportController 487 | responsabilidades excessivas | P2 |
| PERF-007 | opções administrativas carregam coleções completas | crescimento degrada abertura de formulários | P2 |

## Bundle

Build aprovado. Principais artefatos:

- `index` JS: 138,24 KiB (50,24 KiB gzip);
- `ItHelpDeskPage`: 103,54 KiB (23,46 KiB gzip);
- `QBtn`: 88,69 KiB (33,90 KiB gzip);
- CSS: 201,03 KiB (35,74 KiB gzip);
- Transport: 49,28 KiB (10,31 KiB gzip);
- Materials: 24,81 KiB (5,42 KiB gzip).

Não foi executado Lighthouse nem teste de rede lenta.

## Responsividade e acessibilidade

- 375/768/1366 px sem overflow global nas nove rotas;
- drawer responde ao breakpoint;
- modais com dados e tabelas largas não foram todos exercitados em mobile;
- a auditoria original encontrou botões icon-only sem nome acessível; a FIX-018
  corrigiu os 96 casos no código-fonte e adicionou teste de regressão;
- contraste e leitor de tela não foram validados.

## Checklist de produção

O comando `siges:production-check` no ambiente atual retornou:

| Check | Resultado |
|---|---|
| APP_ENV=production | FALHOU |
| APP_DEBUG=false | FALHOU |
| APP_KEY | OK |
| URL HTTPS | FALHOU |
| MySQL | OK |
| cookie seguro | FALHOU |
| fila database | OK |
| retry_after > timeout | OK |
| build frontend | OK |
| storage/cache graváveis | OK |
| mail de produção | FALHOU |
| banco alcançável | OK |
| usuário de banco dedicado | FALHOU |
| ausência de senhas seed padrão | FALHOU |

Esse resultado é esperado para um `.env` local, mas demonstra que o ambiente atual não pode ser promovido diretamente.

## Operação e deploy

Presentes:

- Nginx HTTPS, PHP-FPM, supervisor e systemd;
- queue worker, scheduler, notificações, backup timer;
- health check `/up` respondendo 200;
- documentação de produção e migração MySQL;
- rotina de readiness.

Ausentes/não comprovados:

- CI/CD;
- CSP/HSTS/frame-ancestors no Nginx exemplo;
- monitoramento/APM e alertas de uptime;
- restore testado;
- backup off-site confirmado;
- rotação de logs/segredos documentada;
- execução real de workers/scheduler no host alvo;
- teste de carga controlado.

## Qualidade estática

- todos os PHP analisados passaram em `php -l`;
- Pint encontrou 58 arquivos fora do padrão;
- não há lint/test script de frontend;
- componentes/controllers grandes elevam risco de regressão;
- arquivos legado/template (`SecondPage.vue`, `example-store.js`, `EssentialLink.vue`) parecem não utilizados.

## Classificação

**NÃO PRONTO** para produção. Os bloqueadores são segurança de webhook/autenticação/segredos, dependências vulneráveis e checks de ambiente. Build e testes aprovados não compensam esses riscos.
