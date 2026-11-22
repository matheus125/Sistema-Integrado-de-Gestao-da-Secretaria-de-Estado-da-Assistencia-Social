# 05 — Banco de dados e integrações

## MySQL efetivamente configurado

| Item | Resultado |
|---|---|
| Banco | `siges` |
| Servidor | MySQL 8.4.11 |
| Charset/collation | utf8mb4 / utf8mb4_unicode_ci |
| SQL mode | `ONLY_FULL_GROUP_BY`, `STRICT_TRANS_TABLES`, `NO_ZERO_IN_DATE`, `NO_ZERO_DATE`, `ERROR_FOR_DIVISION_BY_ZERO`, `NO_ENGINE_SUBSTITUTION` |
| Isolamento | REPEATABLE-READ |
| Tabelas | 59 |
| Migrations | 24/24 aplicadas |
| Foreign keys | 99 |
| Entradas de índice | 269 |
| Órfãos por FK | 0 |

As consultas acima foram somente leitura. Nenhum seeder ou migration foi executado contra o MySQL.

## Integridade e desenho

Pontos positivos:

- strict mode e datas inválidas bloqueadas pelo servidor;
- 99 FKs e zero registros órfãos nas relações declaradas;
- uniques para e-mail, placa, códigos e chaves relevantes;
- transações e `lockForUpdate` em estoque e movimentação patrimonial;
- fluxo de transporte mantém histórico e consistência de KM;
- casts para datas, JSON, booleanos e decimais nos models;
- 24 migrations aplicadas e compatibilidade SQLite/MySQL coberta por testes.

Riscos:

- alguns campos de texto importantes não têm limite no backend;
- segredos em `system_settings.value` não são criptografados;
- relatórios agregados e exportações podem percorrer grandes volumes sem paginação;
- testes funcionais usam SQLite, que não reproduz locking, collation e concorrência do MySQL;
- `db:show` revelou que o usuário local possui visibilidade ampla sobre outros schemas; o readiness também marcou usuário `dev` como não dedicado.

## Concorrência

| Cenário | Proteção encontrada | Teste |
|---|---|---|
| Estoque simultâneo | transação + `lockForUpdate` no produto | duas saídas concorrentes aprovadas sem saldo negativo |
| Movimentação de patrimônio | transação + `lockForUpdate` no bem | proteção mantida; fluxo funcional aprovado |
| Agenda de veículo/motorista | transação e locks ordenados nos dois recursos | duas reservas concorrentes resultaram em uma criação e uma rejeição |
| Chamados simultâneos | ID autoincrement, transação e lock da atribuição | IDs únicos e distribuição de carga comprovados com dois processos |
| Aprovação/manutenção | transações pontuais | sem teste concorrente |

Status da FIX-013: **VALIDADO** para estoque, agenda, atribuição e numeração em
MySQL descartável. As transições de aprovação da agenda também usam os novos
locks; manutenção de veículos teve apenas regressão funcional, não um cenário
concorrente dedicado. Carga prolongada permanece para a homologação operacional.

## Integrações

### WhatsApp

- entrada e saída implementadas;
- idempotência por provider ID coberta por teste;
- templates e status de entrega testados;
- nenhuma mensagem externa foi enviada;
- integração fica desabilitada por padrão;
- vulnerabilidade crítica operacional: recepção não valida assinatura da Meta;
- versão da API é configurável no banco, mas o outbound usa `v20.0` hardcoded na URL;
- erro externo pode ser persistido integralmente no log; revisar dados sensíveis.

### E-mail/notificações

- notification/job/listener e comando de health existem;
- no ambiente de teste foi usado mailer array;
- o readiness local marcou transporte de produção como ausente;
- entrega SMTP real foi bloqueada por falta de ambiente/autoridade.

### Exportações

- 18 relatórios;
- CSV, XLSX e PDF baixados em navegador isolado;
- advisories atingem Dompdf e PhpSpreadsheet;
- não houve teste com grande massa ou conteúdo malicioso.

### Gov.br, LDAP e Power BI

Existem apenas chaves de configuração/flags; não foram encontrados fluxos de integração completos. Status: **NÃO IMPLEMENTADA** para operação real.

## Backups e restauração

Há script de backup, serviço/timer systemd e documentação. Na auditoria original,
a restauração não foi executada porque exigia ambiente descartável MySQL e
confirmação de destino. A FIX-008 executou posteriormente dois ciclos em bancos
descartáveis, comparando 59 tabelas, 99 foreign keys, 26 migrations, contagens e
hashes de registros. A capacidade local de restore está **VALIDADA**; restore
periódico/off-site e RPO/RTO permanecem para a FIX-034.
