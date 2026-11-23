# Status das correções da auditoria

Última atualização: 23/11/2026.

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
| FIX-020 | Responsividade de modais | VALIDADA | CSS global, ciclo de foco, roteiro Chrome e relatório `24-responsividade-modais.md` | 27 modais em 375/768/1440 px: 81/81 combinações aprovadas, sem corte/overflow, fuga ou falha de restauração do foco; regressão com 168 testes/1.015 asserções e build SPA aprovados. |
| FIX-021 | Auditoria axe/Playwright | VALIDADA | roteiro axe/teclado, contraste, viewport, semântica ARIA e relatório `25-acessibilidade-axe.md` | 19 varreduras e 171 paradas de teclado: zero violações axe, falhas de foco/teclado ou erros de runtime; regressões Laravel, frontend, modais e build aprovadas. |
| FIX-022 | Paginação/virtualização do Kanban | VALIDADA | API paginada, controles frontend, teste de volume e `26-paginacao-kanban.md` | Cenário com 180 chamados retornou somente 25 itens na página 3; teto 100, 34 testes frontend, 168 testes/1.022 asserções Laravel e build SPA aprovados. |
| FIX-023 | Exportações grandes | VALIDADA | job, armazenamento privado, limites, expiração, frontend e `27-exportacoes-assincronas.md` | 7 cenários/59 asserções direcionados; CSV/XLSX/PDF, autorização, limite, timeout e limpeza aprovados; regressão com 172 testes/1.063 asserções, 37 testes frontend e build SPA. |
| FIX-024 | Limites de paginação | VALIDADA | middleware global, configuração, 16 listagens e `28-limite-global-paginacao.md` | 5 cenários/79 asserções direcionados; extremos recebem 422, limite/default preservados; regressão com 177 testes/1.142 asserções, 37 testes frontend e build SPA. |
| FIX-025 | Refatorações estruturais | VALIDADA | `ReportBuilder`, `ItKanbanBoard`, testes estruturais e `29-refatoracao-estrutural.md` | Controller de relatórios reduzido de 591 para 123 linhas e Kanban extraído da página; 3 testes estruturais, regressão com 177 testes/1.142 asserções, 40 testes frontend e build SPA aprovados. |
| FIX-026 | Vitest | VALIDADA | configuração, 4 suítes unitárias e `30-vitest-componentes.md` | Formulários, login, store e serviço API cobertos por 13 testes Vitest; 40 testes Node, 177 testes/1.142 asserções Laravel, audit npm sem vulnerabilidades e build SPA aprovados. |
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
| 23/11/2026 | FIX-020 | PENDENTE → EM EXECUÇÃO | Inventário encontrou 27 modais; somente o detalhe do chamado limitava altura no mobile e o foco alcançava o `body` ao completar o ciclo de tabulação. |
| 23/11/2026 | FIX-020 | EM EXECUÇÃO → CORRIGIDA | Limites com `vh`/`dvh`, rolagem interna, ações flexíveis e ciclo central de `Tab`/`Shift+Tab` foram implementados sem alterar os fluxos dos formulários. |
| 23/11/2026 | FIX-020 | CORRIGIDA → VALIDADA | Chrome headless aprovou 27 modais em três viewports (81/81), sem corte, overflow, fuga ou falha de refoco; 31 testes frontend, 168 testes/1.015 asserções Laravel e build SPA aprovados. |
| 23/11/2026 | FIX-021 | PENDENTE → EM EXECUÇÃO | A primeira matriz axe encontrou 24 ocorrências críticas/sérias em semântica ARIA e contraste, além de zoom bloqueado, campo dinâmico sem rótulo e foco visual inconsistente. |
| 23/11/2026 | FIX-021 | EM EXECUÇÃO → CORRIGIDA | Viewport, navegação, cores, legendas, estados de carregamento, rótulos dinâmicos e indicador global de foco foram corrigidos; roteiro axe/teclado foi adicionado. |
| 23/11/2026 | FIX-021 | CORRIGIDA → VALIDADA | 19 varreduras axe e 171 paradas de teclado terminaram sem violações ou falhas; 27 modais/81 combinações, 31 testes frontend, 168 testes/1.015 asserções e build SPA aprovados. |
| 23/11/2026 | FIX-022 | PENDENTE → EM EXECUÇÃO | Confirmado que o modo Kanban ignorava `per_page` e executava `get()` com todas as relações da coleção filtrada. |
| 23/11/2026 | FIX-022 | EM EXECUÇÃO → CORRIGIDA | Consulta paginada, metadados/links e controles independentes de página e tamanho foram implementados; o frontend mantém somente a página corrente. |
| 23/11/2026 | FIX-022 | CORRIGIDA → VALIDADA | Teste com 180 chamados confirmou 25 itens na página 3 e transições preservadas; 34 testes frontend, 168 testes/1.022 asserções Laravel e build SPA aprovados. |
| 23/11/2026 | FIX-023 | PENDENTE → EM EXECUÇÃO | Confirmado que CSV, XLSX e PDF eram gerados na requisição e que consultas com `get()` não possuíam limite explícito. |
| 23/11/2026 | FIX-023 | EM EXECUÇÃO → CORRIGIDA | Job dedicado, limites por formato, timeout, arquivo privado temporário, propriedade do download, expiração, limpeza e polling frontend implementados. |
| 23/11/2026 | FIX-023 | CORRIGIDA → VALIDADA | 7 cenários/59 asserções comprovaram os três formatos, limite, autorização, timeout e limpeza; 172 testes/1.063 asserções Laravel, 37 testes frontend e build SPA aprovados. |
| 23/11/2026 | FIX-024 | PENDENTE → EM EXECUÇÃO | Inventário encontrou 16 listagens paginadas; somente quatro validavam explicitamente o teto e as demais repassavam `per_page` ao paginator. |
| 23/11/2026 | FIX-024 | EM EXECUÇÃO → CORRIGIDA | Middleware central e configuração única passaram a validar inteiro entre 1 e 100 antes dos controllers, preservando defaults e autenticação. |
| 23/11/2026 | FIX-024 | CORRIGIDA → VALIDADA | 5 cenários/79 asserções cobriram todas as 16 listagens, extremos, configuração e rotas públicas; 177 testes/1.142 asserções Laravel, 37 testes frontend e build SPA aprovados. |
| 23/11/2026 | FIX-025 | PENDENTE → EM EXECUÇÃO | Confirmado que o controller de relatórios misturava HTTP e 18 consultas, o gerador dependia do controller e a página do Help Desk continha também todo o quadro Kanban. |
| 23/11/2026 | FIX-025 | EM EXECUÇÃO → CORRIGIDA | Consultas movidas para `ReportBuilder`, gerador desacoplado da camada HTTP e quadro/drag-and-drop extraídos para `ItKanbanBoard`, preservando contratos e fluxos. |
| 23/11/2026 | FIX-025 | CORRIGIDA → VALIDADA | 3 testes estruturais, 12 testes direcionados/154 asserções, 177 testes/1.142 asserções Laravel, 40 testes frontend e build SPA aprovados. |
| 23/11/2026 | FIX-026 | PENDENTE → EM EXECUÇÃO | Confirmada a ausência de executor unitário Vue; os 40 testes Node não montavam componentes nem isolavam store e serviço API. |
| 23/11/2026 | FIX-026 | EM EXECUÇÃO → CORRIGIDA | Vitest/JSDOM, scripts e 13 cenários foram adicionados para componentes de formulário, login, autenticação e contratos HTTP simulados. |
| 23/11/2026 | FIX-026 | CORRIGIDA → VALIDADA | 13 testes Vitest e 40 testes Node aprovados; regressão Laravel com 177 testes/1.142 asserções, audit npm em zero e build SPA aprovados. |
