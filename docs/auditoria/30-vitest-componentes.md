# 30 — Vitest e testes de componentes

Data da validação: 23/11/2026.

## Escopo

A FIX-026 adicionou uma suíte unitária permanente com Vitest e ambiente JSDOM
para os contratos frontend que antes eram verificados somente por testes Node
ou indiretamente pelo build. O critério de aceite abrange formulários, store de
autenticação e serviço API. Playwright e navegador real permanecem na FIX-027.

## Infraestrutura

- Vitest 5.1.1 com configuração própria e alias `@` compatível com a aplicação;
- plugin Vue 6.0.11 para compilar os componentes `.vue`;
- JSDOM 31.0.1 como ambiente isolado de DOM;
- montagem feita com a API nativa `createApp` do Vue, evitando uma dependência
  de testes que introduzia advisories moderados;
- comandos `test:unit`, `test:unit:watch`, `test:node` e `test` adicionados ao
  `package.json`;
- `npm test` executa os testes Node existentes e, em seguida, a suíte Vitest.

## Cobertura criada

### Formulários e componentes

- telefone: apresentação, normalização e mensagem de formato inválido;
- placa: forma canônica antiga/Mercosul e rejeição de valor incompleto;
- moeda BRL: exibição, conversão para decimal canônico e limite de dígitos;
- login: envio das credenciais pela store, navegação somente após sucesso e
  notificação sem navegação quando a autenticação falha.

### Store de autenticação

- login, estado `loading`, getters de função/permissão e persistência apenas em
  `sessionStorage`;
- falha de login sem estado de carregamento preso;
- atualização por `/auth/me` e limpeza local mesmo quando o logout remoto
  falha.

### Serviço API

- cabeçalhos JSON e Bearer em requisições autenticadas;
- preservação do status, payload e erros 422 por campo;
- limpeza da sessão e evento global após 401;
- upload com `FormData` sem sobrescrever o `Content-Type` do navegador;
- download de blob, nome de arquivo e revogação da URL temporária.

Todas as credenciais e tokens usados são fictícios. Nenhuma chamada de teste é
enviada ao backend ou a serviços externos: `fetch`, navegação e downloads são
simulados no processo do Vitest.

## Resultados

| Verificação | Resultado |
|---|---:|
| Vitest | 4 arquivos, 13 testes aprovados |
| Testes Node existentes | 40 aprovados |
| Total frontend automatizado | 53 testes, 0 falhas |
| Regressão Laravel | 177 testes, 1.142 asserções |
| Audit npm | 0 vulnerabilidades |
| Build Quasar SPA | aprovado; 39 arquivos JS, 669,74 KB |

A suíte Vitest usa DOM e armazenamentos isolados por teste. Esta correção não
possui migration e não modificou o banco MySQL real.

## Riscos residuais

- JSDOM valida comportamento de componentes, mas não layout, renderização real
  do Quasar ou diferenças entre navegadores;
- componentes Quasar são substituídos por stubs mínimos nos testes do formulário,
  deixando interação visual e acessibilidade renderizada para testes E2E;
- os nove fluxos completos de CRUD ainda precisam de cobertura no navegador;
- métricas e limiares globais de cobertura poderão ser adotados no pipeline,
  depois que a suíte abranger os módulos restantes.

## Conclusão

A FIX-026 está **VALIDADA**. Formulários compartilhados, login, store de
autenticação e serviço API possuem testes Vitest executáveis por um comando
único, sem advisories npm. A FIX-027 não foi iniciada.
