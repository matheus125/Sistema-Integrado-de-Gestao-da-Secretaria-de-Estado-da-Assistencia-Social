# 02 — Testes de frontend e execução real

## Ambiente isolado

Foi criado um SQLite temporário em `/tmp`, executadas as 24 migrations e os seeders, e iniciadas instâncias separadas:

- API Laravel: `127.0.0.1:8011`;
- Quasar: `127.0.0.1:9011`;
- Chrome headless com Playwright reutilizado de outro workspace, sem instalação.

O SQLite, screenshots e instâncias isoladas foram removidos ao final. O MySQL não recebeu os dados de teste. As instâncias de desenvolvimento em 8000/9000 permaneceram em execução.

## Resultados automatizados

| Teste | Resultado |
|---|---|
| `php artisan test` | 84 testes e 468 asserções aprovadas em 5,95 s |
| `npm run build` no Quasar | aprovado; SPA compilada em aproximadamente 1 s |
| Sintaxe PHP | nenhum erro |
| `pint --test` | falhou: 58 arquivos requerem formatação |
| Health `/up` | HTTP 200 |

## Navegador real

| ID | Cenário | Perfil | Resultado | Status |
|---|---|---|---|---|
| E2E-001 | Login e dashboard | Administrador isolado | Renderizou sem erro | PASSOU |
| E2E-002 | Administração | Administrador isolado | Título, tabelas e APIs carregaram | PASSOU |
| E2E-003 | Transporte | Administrador isolado | Painel, opções, agenda e disponibilidade carregaram | PASSOU |
| E2E-004 | Materiais e patrimônio | Administrador isolado | Abas e APIs carregaram | PASSOU |
| E2E-005 | Help Desk | Administrador isolado | Título correto e APIs carregaram | PASSOU |
| E2E-006 | Manutenção preventiva | Administrador isolado | Tela e resumo carregaram | PASSOU |
| E2E-007 | Auditoria | Administrador isolado | Tela e filtros carregaram | PASSOU |
| E2E-008 | Relatórios | Administrador isolado | 18 prévias consultadas | PASSOU |
| E2E-009 | Exportação | Administrador isolado | CSV, XLSX e PDF baixados | PASSOU |
| E2E-010 | Configurações | Administrador isolado | Grupos e segredos mascarados carregaram | PASSOU |
| E2E-011 | Console/rede | Administrador isolado | Zero `pageerror` e zero resposta >=400 no smoke aprovado | PASSOU |
| E2E-012 | Formulário de usuário vazio | Administrador isolado | Regra obrigatória exibida | PASSOU |

O primeiro roteiro de smoke tinha uma suposição fixa sobre a porta 9000 e falhou com 404 na API isolada. O mesmo roteiro foi reexecutado em memória com a URL correta, sem editar o projeto, e passou. A captura inicial de Informática registrou a tela anterior por não aguardar a troca reativa da rota; uma reprodução com espera explícita confirmou URL e título corretos em todas as rotas. Esses dois eventos são limitações do roteiro, não bugs do SIGES.

## Responsividade

Foram exercitadas as nove rotas autenticadas em 375, 768 e 1366 px. Em navegação iniciada em cada largura:

- drawer fechado abaixo do breakpoint;
- `scrollWidth` igual à viewport;
- cabeçalhos dentro da viewport;
- nenhum overflow horizontal global detectado.

A auditoria original não inspecionou cada modal. Em 23/11/2026, a FIX-020
passou a abrir os 27 modais em 375, 768 e 1440 px: as 81 combinações foram
aprovadas sem corte ou overflow e com foco preso/restaurado. Tabelas com dados
volumosos continuam fora dessa matriz específica.

## Integração frontend/API

Confirmado:

- token Bearer enviado pelo serviço central;
- 401 redireciona para login e limpa estado local;
- na auditoria original, a mensagem global usava somente o primeiro erro de
  validação; a FIX-019 passou a mostrar todas as mensagens nos campos;
- formulários usam `QForm`, regras obrigatórias e estados `loading`;
- listas usam paginação e estados vazios;
- exportações tratam Blob e nome de arquivo;
- rotas ocultam módulos por permissão, enquanto o backend também aplica middleware/checagens.

Lacunas identificadas na auditoria original:

- não havia testes JS unitários, de componentes ou E2E declarados no
  `package.json`; as correções incrementais adicionaram testes Node e a FIX-026
  incorporou Vitest/component tests. Playwright permanente continua na FIX-027;
- não foi exercitado pelo navegador cada CRUD, filtro, paginação e exclusão;
- após a FIX-025, `ItHelpDeskPage.vue` ainda concentra 2.245 linhas e nove
  formulários, embora o quadro Kanban tenha sido extraído;
- falhas gerais ainda usam `Notify` e não há política central de retry/offline;
  respostas 422, porém, possuem associação por campo desde a FIX-019.

Atualização de 23/11/2026: o roteiro funcional de modais adicionou cobertura
Chrome headless para limites, rolagem, foco inicial, ciclo de `Tab` e refoco em
27 diálogos. A instalação permanente do Playwright no projeto/CI permanece na
FIX-027.

Atualização de 22/11/2026: a FIX-019 adicionou estado reativo central para
`payload.errors` e vinculou 187 controles submetidos nos 24 `QForm`, além dos
campos dinâmicos de Configurações, filtros de Relatórios e ações do detalhe de
chamado. Cinco testes Node verificam mensagens múltiplas, aliases, chaves
aninhadas e cobertura estática dos formulários.

## Acessibilidade

Uma varredura DOM encontrou controles icon-only sem `aria-label`/nome textual acessível. Contagens observadas por rota incluíram 9 botões sem nome em Administração, 6 no Dashboard, 6 no Help Desk e 8 em Auditoria. Muitos possuem apenas tooltip visual.

Pontos positivos: `h1` por tela, labels visíveis nos campos, `role="alert"` em erro de calendário e atributos ARIA no Kanban.

Na auditoria original não foram executados axe-core, leitor de tela nem teste
completo de contraste. A FIX-021 executou posteriormente 19 varreduras axe nas
dez telas/rotas e verificou 171 paradas de teclado, sem violações ou falhas na
matriz final. A avaliação humana com leitor de tela permanece recomendada.

Atualização de 22/11/2026: a FIX-018 inventariou os componentes `QBtn` no
código-fonte e corrigiu os 96 controles com ícone ou formato redondo que não
possuíam nome explícito. Os 166 controles dessa categoria agora têm
`aria-label`, `label` ou `title`, verificados por teste Node permanente. A
auditoria do DOM renderizado com axe, contraste, teclado e leitor de tela
foi executada pela FIX-021, exceto a avaliação humana com leitor de tela.

Atualização de 23/11/2026: viewport, semântica da navegação, contraste, rótulos
dinâmicos e indicador de foco foram corrigidos. O roteiro Chrome/axe terminou
com zero violações críticas, sérias, moderadas ou menores detectadas pelas tags
WCAG 2/2.1 A/AA adotadas.

Atualização de 23/11/2026: a FIX-022 adicionou paginação própria ao Kanban, com
25 cartões por página por padrão e teto de 100. O teste de volume criou 180
chamados, solicitou a terceira página e confirmou que somente 25 registros
foram entregues e mantidos no estado da tela. A suíte frontend acumulada passou
a 34 testes Node aprovados.

Atualização de 23/11/2026: a FIX-023 substituiu downloads síncronos por
solicitação assíncrona, acompanhamento de estado e download do arquivo privado
concluído. Três testes Node adicionais verificam fila, polling, propriedade,
timeout e falha segura, elevando a suíte frontend acumulada para 37 testes.

Atualização de 23/11/2026: a FIX-025 extraiu colunas, cartões e drag-and-drop
para `ItKanbanBoard.vue`, mantendo filtros, paginação e operações HTTP na
página. Três testes estruturais protegem as novas fronteiras do frontend e do
backend, elevando a suíte frontend acumulada para 40 testes.

Atualização de 23/11/2026: a FIX-026 adicionou Vitest 5 com JSDOM e 13 testes
em quatro arquivos. A nova suíte monta os componentes compartilhados de
telefone, placa, moeda e login; também isola a store de autenticação e os fluxos
de JSON, 422, 401, upload e download do serviço API. `npm test` executa os 40
testes Node e os 13 testes Vitest. O audit npm permaneceu sem vulnerabilidades.

## Bugs funcionais e UX reproduzidos

### BUG-001 — telefone aceita letras

- Módulo: Administração/Informática/Transporte.
- Passos: enviar usuário com `phone=abc` no ambiente isolado.
- Esperado: 422 ou normalização válida.
- Obtido: 201.
- Severidade: média.
- Causa: apenas `string|max:40`, sem máscara/normalização/regex.

Atualização de 13/11/2026: a FIX-009 corrigiu a validação e normalização no
backend dos três módulos. Letras e formatos brasileiros inválidos agora recebem
`422`. A máscara visual permanece para a FIX-014.

### BUG-002 — placa inválida aceita

- Módulo: Transporte.
- Passos: criar veículo com placa `x`.
- Esperado: 422.
- Obtido: 201.
- Severidade: média.
- Causa: apenas `string|max:20`.

Atualização de 13/11/2026: a FIX-010 corrigiu a validação, normalização e
unicidade canônica no backend. `x` agora recebe `422`; a máscara visual permanece
para a FIX-015.

### BUG-003 — controles sem nome acessível

- Módulos: múltiplos.
- Passos: inspecionar botões icon-only na árvore DOM.
- Esperado: nome acessível por `aria-label` ou texto.
- Obtido: diversos botões vazios; tooltip não supre consistentemente o nome.
- Severidade: média para acessibilidade.

Atualização de 22/11/2026: corrigido pela FIX-018 no código-fonte. O teste
automatizado confirma 166/166 botões com nome acessível explícito e falha se um
novo botão com ícone ou formato redondo for adicionado sem nome. A validação
dinâmica com axe permanece na FIX-021.
