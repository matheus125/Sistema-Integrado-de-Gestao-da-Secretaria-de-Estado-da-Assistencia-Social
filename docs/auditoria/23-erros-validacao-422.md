# 23 — Erros HTTP 422 associados aos campos

Data da validação: 22/11/2026.

## Diagnóstico

O serviço de API já anexava o corpo da resposta em `error.payload`, mas a função
de mensagem selecionava somente o primeiro item de `payload.errors`. As telas
capturavam a exceção e exibiam esse texto em uma notificação global. Nenhum
controle consumia o mapa de erros retornado pelo Laravel.

Assim, uma resposta com erros simultâneos em `email`, `phone` e `roles`, por
exemplo, mostrava somente a primeira mensagem fora do formulário.

## Implementação

Foi criado o serviço reativo `server-validation.js`, que:

- preserva todas as chaves e mensagens de `payload.errors`;
- aceita a estrutura de array padrão do Laravel e mensagens escalares;
- resolve campos simples e chaves aninhadas, como `items.0.label`;
- aceita aliases quando o nome visual difere do payload, como os editores JSON
  `conditionsText`/`conditions`;
- concatena, sem duplicar, múltiplas mensagens do mesmo campo;
- fornece as propriedades `error` e `errorMessage` esperadas pelos campos
  Quasar;
- limpa erros anteriores antes de uma nova mutação ou abertura de diálogo.

`apiFetch`, `apiUpload` e `apiDownload` passaram a capturar respostas 422. O
erro continua sendo lançado para preservar notificações e fluxos existentes,
mas as mensagens agora também aparecem diretamente nos respectivos campos.

## Cobertura dos formulários

Foram vinculados os controles de:

- Login e Administração;
- Materiais, Estoque e Patrimônio, inclusive uploads;
- Transporte, Manutenção e checklists;
- Help Desk, SLA, catálogos, templates, automações, mensagens e anexos;
- Manutenção Preventiva e calendários de atendimento;
- Configurações dinâmicas e filtros de Relatórios.

O inventário dos 24 componentes `QForm` encontrou 164 campos de texto, seleção,
arquivo ou componentes especializados e 23 controles de escolha, totalizando
187 controles submetidos. Todos possuem associação explícita. Campos de ações
do detalhe do chamado e telas sem `QForm` também foram tratados separadamente.

O seletor “Usar template” do Help Desk foi marcado como local: ele apenas copia
texto para a mensagem e não integra o payload enviado à API.

## Testes

Foi criada a suíte `frontend/quasar/tests/server-validation.test.js` e o comando:

```bash
npm --prefix frontend/quasar run test:server-validation
```

Resultados:

| Verificação | Resultado |
|---|---:|
| Serviço e cobertura de formulários | 5 testes aprovados, 0 falhas |
| Controles submetidos em `QForm` | 187/187 cobertos |
| Testes frontend acumulados | 31 aprovados, 0 falhas |
| Regressão Laravel completa | 168 testes, 1.015 asserções |
| Build Quasar SPA | aprovado; 38 arquivos JS, 658,72 KB |
| Sintaxe Node e integridade do diff | aprovadas |

Nenhum endpoint, validação backend, schema ou dado do banco foi alterado.

## Riscos residuais

- A cobertura desta etapa verifica o contrato e o vínculo estático dos campos.
  A interação completa no navegador com respostas 422 reais deve integrar a
  futura suíte Playwright da FIX-027.
- Erros gerais sem chave de campo continuam em `Notify`, pois não existe um
  controle correto ao qual associá-los.
- A política de retry/offline para falhas de rede permanece fora do escopo desta
  correção.

## Conclusão

A FIX-019 está **VALIDADA**. Respostas HTTP 422 não dependem mais apenas da
primeira notificação global: todas as mensagens são mantidas e apresentadas no
campo correspondente, incluindo listas aninhadas e campos dinâmicos.
