# Status das correções da auditoria

Última atualização: 22/11/2026.

| ID | Problema | Status | Arquivos/testes | Evidência e observações |
|---|---|---|---|---|
| FIX-001 | Assinatura do webhook WhatsApp | VALIDADA | middleware, rota, configuração, migration de idempotência e `WhatsappWebhookSecurityTest` | 10 cenários direcionados aprovados; regressão com 94 testes/502 asserções e build SPA aprovados. |
| FIX-002 | Dependências vulneráveis | VALIDADA | `composer.lock`, `frontend/quasar/package.json`, `package-lock.json` e `ReportExportTest` | Composer: 42 → 0 advisories; npm: 13 → 0 vulnerabilidades; PDF/XLSX/CSV, 97 testes/520 asserções, build SPA e smoke Chrome aprovados. |
| FIX-003 | Rate limit no login | VALIDADA | `AuthController`, `config/auth.php`, exemplos de ambiente e `AuthRateLimitTest` | 9 cenários/47 asserções direcionados; regressão com 106 testes/567 asserções e build SPA aprovados. |
| FIX-004 | Proteção de segredos | VALIDADA | `SystemSetting`, comando de migração, migration, API, seeder, documentação e `SystemSettingSecretTest` | 7 cenários/33 asserções direcionados; regressão com 113 testes/600 asserções e build SPA aprovados. Conversão dos dados reais reservada ao procedimento administrativo com backup. |
| FIX-005 | Token padrão do webhook | VALIDADA | política de força, seeder, API, verificação pública, readiness e `WhatsappVerifyTokenSecurityTest` | 7 cenários/31 asserções direcionados; regressão com 120 testes/631 asserções e build SPA aprovados. |
| FIX-006 | Sessão e tokens | VALIDADA | política Sanctum, `AuthTokenLifecycleTest`, dispositivos, revogação, limpeza e armazenamento frontend | 9 cenários/56 asserções direcionados; regressão com 129 testes/687 asserções e build SPA aprovados. |
| FIX-007 | CRUD Administração e Materiais | VALIDADA | `AdministrationCrudTest`, `MaterialsCrudTest`, hierarquia de setores e transferência patrimonial | 15 cenários/148 asserções direcionados; regressão com 144 testes/835 asserções e build SPA aprovados. |
| FIX-008 | Restauração MySQL | VALIDADA | `backup-mysql.php` e relatório de restauração | 59 tabelas, 56 registros, 99 FKs e 26 migrations comparados; regressões MySQL/SQLite com 144 testes/835 asserções e build SPA aprovados. |
| FIX-009 | Telefones | VALIDADA | regra central, auditoria legada e `BrazilianPhoneValidationTest` | 7 cenários/53 asserções novos; regressão com 151 testes/888 asserções e build SPA aprovados. Um telefone legado inválido foi preservado para correção administrativa. |
| FIX-010 | Placas | VALIDADA | regra canônica, auditoria legada e `BrazilianVehiclePlateValidationTest` | 6 cenários/46 asserções novos; regressão com 157 testes/934 asserções e build SPA aprovados. As 2 placas existentes são válidas, canônicas e únicas. |
| FIX-011 | CNH e RENAVAM | AGUARDANDO DEFINIÇÃO | regra e auditoria de RENAVAM, inventário de CNH e `TransportIdentifierValidationTest` | RENAVAM corrigido com regra oficial de 11 dígitos e checksum; CNH não alterada porque o campo não identifica se armazena registro nacional, espelho ou formulário RENACH. Regressão com 161 testes/954 asserções e build SPA aprovados. |
| FIX-012 | Limites de campos | VALIDADA | validações Laravel, formulários Quasar, helper compartilhado e `FieldLengthValidationTest` | Limites backend ausentes e `maxlength`/mensagens frontend implementados; 5 testes/43 asserções direcionados, regressão com 166 testes/997 asserções e build SPA aprovados. |
| FIX-013 | Concorrência | VALIDADA | locks de agenda/atribuição, `MysqlConcurrencyTest` e executor MySQL descartável | Estoque, transporte, atribuição e IDs testados em processos simultâneos: 1 teste/22 asserções MySQL; regressão com 166 testes/997 asserções e build SPA aprovados. |
| FIX-014 | Máscara de telefone | VALIDADA | `BrazilianPhoneInput`, utilitário e teste Node | 6 testes frontend, 6 testes/49 asserções de integração backend, regressão com 166 testes/997 asserções e build SPA aprovados. |
| FIX-015 | Máscara de placa | VALIDADA | `BrazilianVehiclePlateInput`, utilitário e teste Node | 6 testes frontend, 6 testes/46 asserções backend, regressão com 166 testes/997 asserções e build SPA aprovados. |
| FIX-016 | Máscara de moeda | VALIDADA | `BrlCurrencyInput`, utilitário, validação decimal e testes | 7 testes frontend; 2 cenários/18 asserções backend novos; regressão com 168 testes/1.015 asserções e build SPA aprovados. |
| FIX-017 | Datas | VALIDADA | composable central, tabelas, sessões, relatórios e teste Node | 6 testes frontend; regressão com 168 testes/1.015 asserções e build SPA aprovados; payloads dos inputs permanecem ISO. |
| FIX-018 | Nomes acessíveis | VALIDADA | `aria-label` contextual e teste Node | 166/166 botões com ícone ou formato redondo possuem nome explícito; regressão com 168 testes/1.015 asserções e build SPA aprovados. |
| FIX-019 | Erros 422 por campo | VALIDADA | serviço reativo, API, 24 formulários e teste Node | 187 controles submetidos cobertos, além de configurações, relatórios e ações do chamado; 5 testes direcionados, regressão com 168 testes/1.015 asserções e build SPA aprovados. |
| FIX-020 | Responsividade de modais | PENDENTE | — | Não iniciada. |
| FIX-021 | Auditoria axe/Playwright | PENDENTE | — | Não iniciada. |
| FIX-022 | Paginação/virtualização do Kanban | PENDENTE | — | Não iniciada. |
| FIX-023 | Exportações grandes | PENDENTE | — | Não iniciada. |
| FIX-024 | Limites de paginação | PENDENTE | — | Não iniciada. |
| FIX-025 | Refatorações estruturais | PENDENTE | — | Não iniciada. |
| FIX-026 | Vitest | PENDENTE | — | Não iniciada. |
| FIX-027 | Playwright | PENDENTE | — | Não iniciada. |
| FIX-028 | Testes negativos/IDOR | PENDENTE | — | Não iniciada. |
| FIX-029 | Cobertura permanente do webhook | PENDENTE | — | Reutilizar a suíte criada na FIX-001. |
| FIX-030 | CI/CD | PENDENTE | — | Não iniciada. |
| FIX-031 | Nginx e headers | PENDENTE | — | Não iniciada. |
| FIX-032 | Configuração segura de produção | PENDENTE | — | Não iniciada. |
| FIX-033 | Monitoramento | PENDENTE | — | Não iniciada. |
| FIX-034 | Backups/RPO/RTO | PENDENTE | — | Não iniciada. |

## Histórico

| Data | FIX | Transição | Evidência |
|---|---|---|---|
| 12/11/2026 | FIX-001 | PENDENTE → EM EXECUÇÃO | Vulnerabilidade SEC-001 reconfirmada no controller e na rota pública; implementação e testes adicionados. |
| 12/11/2026 | FIX-001 | EM EXECUÇÃO → CORRIGIDA | HMAC-SHA256 sobre corpo bruto, falha segura, middleware no POST e idempotência persistida implementados. |
| 12/11/2026 | FIX-001 | CORRIGIDA → VALIDADA | 10 testes de segurança aprovados; suíte Laravel completa com 94 testes/502 asserções e build Quasar aprovados. |
| 12/11/2026 | FIX-002 | PENDENTE → EM EXECUÇÃO | Audits reconfirmaram 42 advisories Composer e 13 vulnerabilidades npm; cadeias afetadas e versões corrigidas mapeadas. |
| 12/11/2026 | FIX-002 | EM EXECUÇÃO → CORRIGIDA | Lockfiles atualizados de forma controlada, sem troca das versões principais Laravel 12, Quasar 2 e Vue 3; audits passaram a zero. |
| 12/11/2026 | FIX-002 | CORRIGIDA → VALIDADA | CSV, XLSX e PDF aprovados; suíte completa com 97 testes/520 asserções, build SPA, smoke Chrome, validação Composer e audits aprovados. |
| 12/11/2026 | FIX-003 | PENDENTE → EM EXECUÇÃO | Fluxo sem proteção reconfirmado; política configurável e contadores combinados implementados para validação. |
| 12/11/2026 | FIX-003 | EM EXECUÇÃO → CORRIGIDA | Falhas passaram a alimentar limites por identificador normalizado+IP e por IP; excesso retorna 429 com `Retry-After`. |
| 12/11/2026 | FIX-003 | CORRIGIDA → VALIDADA | 9 testes direcionados/47 asserções, suíte completa com 106 testes/567 asserções e build Quasar aprovados. |
| 12/11/2026 | FIX-004 | PENDENTE → EM EXECUÇÃO | Dois segredos em `system_settings` inventariados; estratégia incremental com fallback legado e comando protegido por backup implementada. |
| 12/11/2026 | FIX-004 | EM EXECUÇÃO → CORRIGIDA | Coluna criptografada separada, leitura e gravação seguras, API mascarada, rollback verificável e migração/rotação local controladas foram implementados sem converter dados reais. |
| 12/11/2026 | FIX-004 | CORRIGIDA → VALIDADA | 7 testes direcionados/33 asserções, suíte completa com 113 testes/600 asserções e build Quasar aprovados. |
| 13/11/2026 | FIX-005 | PENDENTE → EM EXECUÇÃO | Fallback e valor fixo reconfirmados no controller e no seeder; política central e bloqueios de configuração implementados para validação. |
| 13/11/2026 | FIX-005 | EM EXECUÇÃO → CORRIGIDA | Seeder sem segredo fixo, configuração por ambiente, verificação em falha segura e bloqueio transacional de ativação com token inseguro implementados. |
| 13/11/2026 | FIX-005 | CORRIGIDA → VALIDADA | 7 testes direcionados/31 asserções, suíte completa com 120 testes/631 asserções e build Quasar aprovados. |
| 13/11/2026 | FIX-006 | PENDENTE → EM EXECUÇÃO | Tokens sem expiração e Bearer persistente em `localStorage` reconfirmados; política de ciclo de vida e mitigação frontend implementadas para validação. |
| 13/11/2026 | FIX-006 | EM EXECUÇÃO → CORRIGIDA | Expiração absoluta, inatividade, limite de dispositivos, revogação individual/global, bloqueio por status, limpeza diária e `sessionStorage` implementados. |
| 13/11/2026 | FIX-006 | CORRIGIDA → VALIDADA | 9 testes direcionados/56 asserções, suíte completa com 129 testes/687 asserções e build Quasar aprovados; migração para cookie ficou documentada para homologação E2E futura. |
| 13/11/2026 | FIX-007 | PENDENTE → EM EXECUÇÃO | Lacunas de CRUD, validação, permissões e escopo reconfirmadas; suítes específicas de Administração e Materiais/Patrimônio adicionadas. |
| 13/11/2026 | FIX-007 | EM EXECUÇÃO → CORRIGIDA | Ciclos na hierarquia de setores e erro 500 em transferência sem destino corrigidos com validação 422; cobertura CRUD/RBAC concluída. |
| 13/11/2026 | FIX-007 | CORRIGIDA → VALIDADA | 15 testes direcionados/148 asserções, suíte completa com 144 testes/835 asserções e build Quasar aprovados. |
| 13/11/2026 | FIX-008 | PENDENTE → EM EXECUÇÃO | Banco MySQL descartável criado; 26 migrations e seeders aplicados sem acessar ou alterar o banco configurado no projeto. |
| 13/11/2026 | FIX-008 | EM EXECUÇÃO → CORRIGIDA | Modo isolado protegido e descartável adicionado; restore passou a validar hashes/contagens, 99 FKs, migrations e `CHECK TABLE`. |
| 13/11/2026 | FIX-008 | CORRIGIDA → VALIDADA | Dois ciclos de restore aprovados; suíte completa em MySQL e SQLite com 144 testes/835 asserções e build Quasar aprovados. |
| 13/11/2026 | FIX-009 | PENDENTE → EM EXECUÇÃO | Backend aceitava texto livre nos três módulos; regra brasileira e estratégia de normalização definidas após inventário dos fluxos. |
| 13/11/2026 | FIX-009 | EM EXECUÇÃO → CORRIGIDA | Validação/normalização central aplicada em Administração, Transporte, Help Desk e entrada WhatsApp; comando somente leitura para legado adicionado. |
| 13/11/2026 | FIX-009 | CORRIGIDA → VALIDADA | 7 cenários novos/53 asserções e regressão com 151 testes/888 asserções aprovados; build Quasar aprovado. Auditoria encontrou 1 legado inválido, sem alteração automática ou constraint. |
| 13/11/2026 | FIX-010 | PENDENTE → EM EXECUÇÃO | API aceitava qualquer string e a unicidade dependia da representação armazenada; padrões antigo/Mercosul e forma canônica definidos. |
| 13/11/2026 | FIX-010 | EM EXECUÇÃO → CORRIGIDA | Validação, normalização anterior ao unique, comparação canônica contra legado/soft-deleted, busca normalizada e auditoria somente leitura implementadas. |
| 13/11/2026 | FIX-010 | CORRIGIDA → VALIDADA | 6 cenários novos/46 asserções e regressão com 157 testes/934 asserções aprovados; build Quasar aprovado. Duas placas reais auditadas sem pendências. |
| 13/11/2026 | FIX-011 | PENDENTE → EM EXECUÇÃO | Campos, fluxos e dados existentes inventariados; normas oficiais confirmaram uma regra inequívoca para RENAVAM e três identificadores distintos no documento de habilitação. |
| 13/11/2026 | FIX-011 | EM EXECUÇÃO → AGUARDANDO DEFINIÇÃO | RENAVAM corrigido e coberto por quatro testes; suíte completa com 161 testes/954 asserções e build Quasar aprovados. CNH permanece sem regra semântica até a escolha institucional do identificador. |
| 13/11/2026 | FIX-012 | PENDENTE → EM EXECUÇÃO | Comparação confirmou limites Laravel ausentes em textos longos e ausência total de `maxlength` nos formulários; comprimentos reais foram medidos sem exibir ou alterar valores. |
| 13/11/2026 | FIX-012 | EM EXECUÇÃO → EM VALIDAÇÃO | Limites backend e frontend alinhados, mensagens/contadores adicionados e 5 testes/43 asserções direcionados aprovados; build Quasar aprovado. |
| 13/11/2026 | FIX-012 | EM VALIDAÇÃO → VALIDADA | Regressão completa com 166 testes/997 asserções e build Quasar SPA aprovados; nenhum dado real ou schema foi alterado. |
| 14/11/2026 | FIX-013 | PENDENTE → EM EXECUÇÃO | Estoque já possuía lock; foram confirmadas janelas entre consulta e escrita na agenda e na atribuição automática. |
| 14/11/2026 | FIX-013 | EM EXECUÇÃO → CORRIGIDA | Locks ordenados e transações passaram a proteger agenda, transições e seleção por carga; numeração permaneceu no AUTO_INCREMENT do MySQL. |
| 14/11/2026 | FIX-013 | CORRIGIDA → VALIDADA | Dois processos por cenário em MySQL descartável aprovaram 22 asserções; regressão com 166 testes/997 asserções e build Quasar aprovados. Zero schemas temporários remanescentes. |
| 22/11/2026 | FIX-014 | PENDENTE → EM EXECUÇÃO | Os três campos de telefone local foram mapeados e o contrato de `v-model` normalizado foi alinhado à regra backend da FIX-009. |
| 22/11/2026 | FIX-014 | EM EXECUÇÃO → CORRIGIDA | Componente e utilitário compartilhados passaram a tratar fixo/celular, digitação, colagem, exclusão, cursor, edição e teclado móvel. |
| 22/11/2026 | FIX-014 | CORRIGIDA → VALIDADA | 6 testes frontend e 6 testes/49 asserções backend aprovados; regressão Laravel com 166 testes/997 asserções e build Quasar aprovados. |
| 22/11/2026 | FIX-015 | PENDENTE → EM EXECUÇÃO | O campo de placa do cadastro de veículos e o contrato canônico já protegido pela FIX-010 foram confirmados. |
| 22/11/2026 | FIX-015 | EM EXECUÇÃO → CORRIGIDA | Componente e utilitário compartilhados passaram a tratar placa antiga/Mercosul, caixa, colagem, hífen, cursor e edição. |
| 22/11/2026 | FIX-015 | CORRIGIDA → VALIDADA | 6 testes frontend e 6 testes/46 asserções backend aprovados; regressão Laravel com 166 testes/997 asserções e build Quasar aprovados. |
| 22/11/2026 | FIX-016 | PENDENTE → EM EXECUÇÃO | Valor de patrimônio e custo de manutenção foram confirmados como os dois campos monetários editáveis, ambos persistidos em `DECIMAL(12,2)`. |
| 22/11/2026 | FIX-016 | EM EXECUÇÃO → CORRIGIDA | Componente BRL compartilhado passou a emitir string decimal canônica; APIs passaram a rejeitar precisão e faixa incompatíveis com o banco. |
| 22/11/2026 | FIX-016 | CORRIGIDA → VALIDADA | 7 testes frontend e 2 cenários/18 asserções backend novos aprovados; regressão com 168 testes/1.015 asserções e build Quasar aprovados. |
| 22/11/2026 | FIX-017 | PENDENTE → EM EXECUÇÃO | O helper de data/hora ainda exibia ISO e quatro tabelas apresentavam datas sem formatação; inputs e contratos ISO foram inventariados. |
| 22/11/2026 | FIX-017 | EM EXECUÇÃO → CORRIGIDA | Helper central passou a apresentar data/hora em pt-BR, tratar fuso e valores inválidos; tabelas, sessões e prévia de relatórios foram alinhadas. |
| 22/11/2026 | FIX-017 | CORRIGIDA → VALIDADA | 6 testes frontend, regressão Laravel com 168 testes/1.015 asserções e build Quasar aprovados; inputs nativos continuam enviando ISO. |
| 22/11/2026 | FIX-018 | PENDENTE → EM EXECUÇÃO | A inspeção estática encontrou 96 de 166 botões com ícone ou formato redondo sem nome acessível explícito em nove arquivos Vue. |
| 22/11/2026 | FIX-018 | EM EXECUÇÃO → CORRIGIDA | Foram adicionados nomes contextuais, inclusive dinâmicos quando necessário, preservando ícones, tooltips, layout e comportamento. |
| 22/11/2026 | FIX-018 | CORRIGIDA → VALIDADA | Inventário estático confirmou 166/166 controles nomeados; teste Node, regressão Laravel com 168 testes/1.015 asserções e build Quasar aprovados. |
| 22/11/2026 | FIX-019 | PENDENTE → EM EXECUÇÃO | Confirmado que o serviço preservava `payload.errors`, mas as telas exibiam somente a primeira mensagem em `Notify`, sem associação aos controles. |
| 22/11/2026 | FIX-019 | EM EXECUÇÃO → CORRIGIDA | Registro reativo passou a mapear mensagens simples, múltiplas e aninhadas; formulários, uploads, configurações, relatórios e ações do chamado foram vinculados aos campos. |
| 22/11/2026 | FIX-019 | CORRIGIDA → VALIDADA | Teste estático confirmou 187/187 controles submetidos cobertos em 24 `QForm`; 5 testes direcionados, regressão com 168 testes/1.015 asserções e build Quasar aprovados. |
