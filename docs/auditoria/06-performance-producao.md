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
| PERF-001 | Corrigido pela FIX-022: Kanban usa paginação no banco | carga limitada a 25 por padrão e 100 no máximo | RESOLVIDO |
| PERF-002 | Corrigido pela FIX-023: exportações em fila e limitadas | memória/tempo limitados por formato e job | RESOLVIDO |
| PERF-003 | Corrigido pela FIX-024: teto global de `per_page` | valores fora de 1–100 recebem 422 antes da consulta | RESOLVIDO |
| PERF-004 | Mitigado pela FIX-025: Kanban extraído; página ainda tem 2.245 linhas | manutenção ainda concentrada nos nove fluxos de formulário | MITIGADO |
| PERF-005 | `TransportPage.vue` tem 1.482 linhas e controller 827 | complexidade e regressão | P2 |
| PERF-006 | Mitigado pela FIX-025: ReportController tem 123 linhas; ItTicketController permanece grande | responsabilidade de relatórios separada; Help Desk backend ainda concentrado | MITIGADO |
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

Atualização de 23/11/2026: a FIX-022 removeu o `get()` irrestrito do Kanban. A
API agora pagina a consulta antes de carregar relações e retorna metadados e
links; o frontend mantém somente a página corrente, permite 15/25/50/100
cartões e reinicia na primeira página ao alterar filtros. Um cenário com 180
chamados confirmou 25 itens na página 3, total 180 e transições preservadas.
O build posterior permaneceu aprovado, com 39 arquivos JS (667,17 KB) e CSS de
219,44 KB.

Atualização de 23/11/2026: a FIX-023 removeu a geração pesada da requisição
HTTP. CSV, XLSX e PDF são processados na fila `exports`, com timeout de 120 s,
limites padrão de 10.000/5.000/500 linhas e arquivo privado por 60 minutos. As
consultas carregam no máximo o limite mais um registro para detectar excesso;
nesse caso o job falha de forma controlada e não publica arquivo. O build
posterior permaneceu aprovado, com 39 arquivos JS (668,39 KB).

Atualização de 23/11/2026: a FIX-024 inventariou as 16 listagens paginadas e
adicionou validação central ao grupo autenticado da API. `per_page` aceita
somente inteiro entre 1 e `PAGINATION_MAX_PER_PAGE` (100 por padrão); vazio,
zero, negativo, decimal, texto, array e valores acima do teto recebem 422 antes
da execução do controller. Paginações padrão existentes foram preservadas.

Atualização de 23/11/2026: a FIX-025 separou as 18 consultas de relatório em
`ReportBuilder`, reduziu `ReportController` de 591 para 123 linhas e removeu a
dependência do gerador de exportações na camada HTTP. No frontend, colunas,
cartões e drag-and-drop foram extraídos para `ItKanbanBoard`; filtros,
paginação, API e transições permanecem na página. A redução é incremental, pois
`ItHelpDeskPage.vue` ainda concentra 2.245 linhas e nove fluxos de formulário.

## Responsividade e acessibilidade

- 375/768/1366 px sem overflow global nas nove rotas;
- drawer responde ao breakpoint;
- a FIX-020 exercitou os 27 modais em 375/768/1440 px e aprovou 81/81
  combinações sem corte/overflow, com rolagem e foco controlados;
- a auditoria original encontrou botões icon-only sem nome acessível; a FIX-018
  corrigiu os 96 casos no código-fonte e adicionou teste de regressão;
- a FIX-021 aprovou contraste, semântica e teclado em 19 varreduras axe e 171
  paradas de foco; leitor de tela humano permanece como risco residual.

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
- a FIX-026 adicionou scripts Vitest e um comando frontend unificado; lint
  estático dedicado ainda não foi incorporado;
- componentes/controllers grandes elevam risco de regressão;
- arquivos legado/template (`SecondPage.vue`, `example-store.js`, `EssentialLink.vue`) parecem não utilizados.

## Classificação

**NÃO PRONTO** para produção. Os bloqueadores são segurança de webhook/autenticação/segredos, dependências vulneráveis e checks de ambiente. Build e testes aprovados não compensam esses riscos.
