# 27 — Exportações assíncronas e limitadas

Data da validação: 23/11/2026.

## Escopo

A FIX-023 tratou PERF-002 e SEC-008 nos relatórios CSV, XLSX e PDF. O objetivo
foi retirar a geração pesada da requisição HTTP, limitar memória/tempo e
proteger o ciclo de vida do arquivo. Limites globais das demais paginações
permanecem na FIX-024.

## Diagnóstico

O endpoint `GET /api/reports/export` construía o relatório completo durante a
requisição. As consultas terminavam em `get()` sem limite e os resultados eram
convertidos integralmente para arrays antes de gerar CSV, XLSX ou PDF.

Consequências confirmadas no código:

- memória e tempo cresciam com todo o resultado filtrado;
- PHP-FPM permanecia ocupado durante a geração;
- não havia timeout específico nem estado consultável;
- não havia expiração ou limpeza de arquivos porque a resposta era imediata;
- o fluxo não tinha um recurso persistente cuja propriedade pudesse ser
  verificada no download.

## Correção aplicada

- `POST /api/reports/exports` registra uma solicitação UUID e responde `202`;
- o endpoint legado passou a enfileirar, sem gerar arquivo durante a requisição;
- `GenerateReportExport` usa fila dedicada, uma tentativa, timeout configurável
  e falha em caso de timeout;
- consultas do job carregam no máximo o limite do formato mais um registro;
- o registro excedente detecta excesso sem materializar o conjunto inteiro;
- limites padrão: CSV 10.000, XLSX 5.000 e PDF 500 linhas;
- arquivos são armazenados no disco privado, com expiração padrão de 60 minutos;
- apenas o usuário solicitante pode consultar estado ou baixar o arquivo;
- downloads pendentes retornam 409, expirados retornam 410 e arquivos ausentes
  retornam 404;
- erros internos não são expostos; somente a mensagem controlada de excesso é
  apresentada ao usuário;
- o scheduler executa `reports:prune-exports` a cada hora;
- o frontend acompanha `pending`/`processing` por polling e baixa somente após
  `completed`;
- o worker systemd passou a consumir `exports,notifications,default`.

## Configuração administrativa

| Variável | Padrão | Finalidade |
|---|---:|---|
| `REPORT_EXPORT_DISK` | `local` | disco privado dos arquivos |
| `REPORT_EXPORT_QUEUE` | `exports` | fila dedicada |
| `REPORT_EXPORT_TIMEOUT` | `120` | timeout do job em segundos |
| `REPORT_EXPORT_EXPIRES_AFTER` | `60` | validade do arquivo em minutos |
| `REPORT_EXPORT_CSV_MAX_ROWS` | `10000` | limite CSV |
| `REPORT_EXPORT_XLSX_MAX_ROWS` | `5000` | limite XLSX |
| `REPORT_EXPORT_PDF_MAX_ROWS` | `500` | limite PDF |

O `DB_QUEUE_RETRY_AFTER` deve permanecer maior que o timeout. O modelo entregue
usa 180 segundos de reserva e 120 segundos de timeout.

## Testes

Os sete cenários direcionados comprovam:

1. CSV, XLSX e PDF são enfileirados, gerados e baixados corretamente;
2. o endpoint legado também enfileira;
3. volume acima do limite falha sem publicar arquivo;
4. outro usuário recebe 403 no status e no download;
5. arquivos pendentes e expirados não podem ser baixados;
6. fila, timeout, tentativa única e `failOnTimeout` seguem a configuração;
7. o comando de limpeza remove arquivo e registro expirados.

## Resultados

| Verificação | Resultado |
|---|---:|
| Testes direcionados Laravel | 7 aprovados, 59 asserções |
| Testes frontend específicos | 3 aprovados, 0 falhas |
| Regressão Laravel | 172 testes, 1.063 asserções |
| Regressão frontend | 37 testes, 0 falhas |
| Pint nos arquivos alterados | aprovado |
| Build Quasar SPA | aprovado; 39 arquivos JS, 668,39 KB |

Os testes utilizaram SQLite isolado e armazenamento falso. A nova migration não
foi aplicada ao MySQL real e nenhum arquivo de relatório real foi enviado.

## Riscos residuais

- worker e scheduler precisam permanecer ativos e monitorados; sem worker, as
  solicitações continuam pendentes;
- limites maiores devem ser homologados conforme memória, CPU e tempo do host;
- solicitações que nunca chegaram a iniciar por indisponibilidade prolongada da
  fila precisam de monitoramento operacional;
- o frontend acompanha a solicitação enquanto a tela permanece aberta; o
  histórico persistente de exportações não foi incluído nesta etapa;
- teste de carga concorrente com múltiplas exportações deve ser realizado em
  homologação semelhante à produção.

## Conclusão

A FIX-023 está **VALIDADA**. Exportações autorizadas continuam disponíveis nos
três formatos, mas agora são processadas fora da requisição, dentro de limites
explícitos e com download privado temporário. A FIX-024 não foi iniciada.
