# 08 — Resumo executivo

## Diagnóstico geral do SIGES

O SIGES está em estágio avançado de desenvolvimento/homologação. A arquitetura funcional cobre os oito módulos planejados e cinco domínios transversais. Transporte e Help Desk possuem regras operacionais maduras e boa cobertura backend; Administração e Materiais têm lacunas relevantes de testes; segurança e operação impedem produção.

| Indicador | Total |
|---|---:|
| Módulos RBAC | 8 |
| Domínios transversais | 5 |
| Funcionalidades inventariadas | 83 |
| Com alguma execução/evidência dinâmica | 66 |
| Funcionando integralmente | 50 |
| Parciais | 11 |
| Com erro | 5 |
| Não implementadas | 5 |
| Não testadas | 10 |
| Bloqueadas | 2 |
| Cobertura de execução | 79,5% |
| Aprovação integral entre itens executados | 75,8% |

Cobertura de execução e aprovação são métricas distintas: uma falha reproduzida conta como executada, mas não aprovada.

## Resumo por domínio

| Domínio | Total | Funcionando | Parcial | Erro | Não impl. | Não testada | Bloq. | Execução |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Autenticação | 6 | 3 | 0 | 0 | 3 | 0 | 0 | 50,0% |
| Core/Dashboard | 5 | 5 | 0 | 0 | 0 | 0 | 0 | 100% |
| Administração | 4 | 1 | 0 | 0 | 0 | 3 | 0 | 25,0% |
| Transporte | 8 | 6 | 1 | 0 | 0 | 1 | 0 | 87,5% |
| Materiais/Patrimônio | 5 | 0 | 1 | 0 | 0 | 4 | 0 | 20,0% |
| Informática/Help Desk | 29 | 23 | 4 | 1 | 0 | 1 | 0 | 96,6% |
| Relatórios | 3 | 2 | 1 | 0 | 0 | 0 | 0 | 100% |
| Auditoria | 2 | 1 | 0 | 0 | 0 | 1 | 0 | 50,0% |
| Configurações | 3 | 2 | 0 | 1 | 0 | 0 | 0 | 100% |
| Banco | 4 | 3 | 1 | 0 | 0 | 0 | 0 | 100% |
| Frontend/UX | 7 | 2 | 2 | 2 | 1 | 0 | 0 | 85,7% |
| Operação | 6 | 2 | 1 | 0 | 1 | 0 | 2 | 50,0% |
| Segurança/dependências | 1 | 0 | 0 | 1 | 0 | 0 | 0 | 100% |

## Segurança

### Confirmadas

- críticas: 0 classificadas como exploração confirmada;
- altas: 3 — webhook sem assinatura, login sem rate limit, segredos sem criptografia;
- médias: 2 — token sem expiração e verify token padrão;
- baixas: headers incompletos como defesa em profundidade.

### Riscos potenciais

- 42 advisories PHP, incluindo 1 crítico e 15 altos;
- 13 pacotes JS afetados, incluindo 1 crítico e 9 altos;
- token em `localStorage` amplia impacto de XSS;
- exportações sem limite podem causar exaustão de recursos;
- testes IDOR não cobrem todo endpoint sensível.

Bloqueadores: webhook, dependências, rate limit, segredos e ambiente de produção inseguro.

## Frontend

- telas testadas: login + 9 rotas autenticadas;
- formulários inventariados: 24; testes interativos concentrados em login/usuário e APIs;
- máscaras: zero implementadas na auditoria original, quando telefone e placa
  também falharam na validação. As FIX-009/FIX-010 corrigiram a validação
  backend e as FIX-014/FIX-015/FIX-016 adicionaram os componentes visuais de
  telefone, placa e moeda BRL; a FIX-017 padronizou datas e horas exibidas sem
  alterar os payloads ISO;
- responsividade: 375/768/1366 sem overflow global nas rotas testadas;
- bugs visuais confirmados: nenhum bloqueador;
- acessibilidade: a auditoria original encontrou vários botões sem nome; a
  FIX-018 corrigiu 96 casos e confirmou 166/166 controles com nome explícito;
- validação: a FIX-019 associou respostas 422 aos respectivos campos em 24
  formulários, incluindo mensagens múltiplas e chaves aninhadas;
- integração: smoke aprovado sem erro de console/rede após correção da URL do roteiro;
- testes JS próprios: ausentes na auditoria original; após as correções
  incrementais, 31 testes Node estão aprovados.

## Backend

- 143 rotas API;
- pelo menos 53 padrões literais de endpoint exercitados pela suíte, além de 18 variantes de relatório e chamadas E2E;
- 84 testes/468 asserções aprovados;
- 401, 403, 404 e 422 exercitados; 201 confirmado em criações; 409 não é adotado explicitamente;
- nenhuma falha IDOR confirmada;
- falhas na auditoria original: webhook, rate limit, telefone/placa, expiração e
  segredos; as respectivas correções incrementais estão registradas no status;
- arquitetura: controllers de 827/746/487 linhas e página Vue de 2.220 linhas.

## Banco

- MySQL 8.4.11, utf8mb4, strict mode;
- 24 migrations aplicadas;
- 59 tabelas, 99 FKs, 269 entradas de índice;
- zero órfãos detectados;
- transações/locks em estoque e patrimônio;
- concorrência real MySQL ainda não validada;
- nenhum risco imediato de perda de dados foi reproduzido;
- na auditoria original, a restauração de backup não foi executada; a FIX-008
  validou posteriormente backup/restore em MySQL descartável, com 59 tabelas,
  99 FKs, 26 migrations e contagens/hashes íntegros;

## Help Desk

| Fase | Estado | Evidência |
|---|---|---|
| A — Fundação | CONCLUÍDA | categorias, tipos, paginação, datas, notas, timeline e cancelamento testados |
| B — Gestão operacional | CONCLUÍDA | equipes, atribuição, SLA/calendário, escalonamento, notificações e Kanban |
| C — Experiência | PARCIAL | meus chamados, avaliação, templates e conhecimento funcionam; WhatsApp é inseguro/não validado externamente |
| D — Gestão técnica | CONCLUÍDA | patrimônio, causa, checklists, tarefas, relações e incidentes presentes; partes com cobertura parcial |
| E — Automação | CONCLUÍDA | regras, catálogo, aprovações, impacto/urgência e preventiva testados |
| F — Gestão e BI | CONCLUÍDA | dashboards, indicadores, recorrência e 18 relatórios |
| G — Consolidação | PARCIAL | testes backend e deploy evoluíram; segurança, frontend tests, máscaras, CI e produção pendentes |

Fase atual: **G — Consolidação parcial**. Última fase sequencial considerada concluída: **F — Gestão e BI**.

## Prontidão para produção

### NÃO PRONTO

Justificativa baseada em evidências:

1. webhook anônimo criou chamado no banco isolado;
2. login aceitou dez tentativas sem 429;
3. dependências têm avisos críticos/altos;
4. segredos são JSON não criptografado;
5. readiness local falha em debug, HTTPS, cookie, mail, usuário DB e senhas padrão;
6. CI/CD, restore e monitoramento não foram comprovados na auditoria original;
   a FIX-008 comprovou depois o restore local descartável, permanecendo pendentes
   CI/CD, monitoramento e restore periódico/off-site.

## Próximas 10 ações recomendadas

1. Validar assinatura do webhook e testar replay.
2. Atualizar dependências vulneráveis com regressão completa.
3. Adicionar rate limiting ao login.
4. Criptografar e rotacionar segredos; eliminar defaults.
5. Configurar expiração/inatividade de tokens.
6. Fazer o production-check passar em ambiente de homologação semelhante à produção.
7. Cobrir CRUD/IDOR de Administração e Materiais.
8. Validar e mascarar telefone, placa e moeda.
9. Paginar Kanban e tornar exportações seguras/assíncronas.
10. Implantar CI, monitoramento e teste periódico de restore.
