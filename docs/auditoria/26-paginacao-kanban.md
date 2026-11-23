# 26 — Paginação do Kanban

Data da validação: 23/11/2026.

## Escopo

A FIX-022 tratou exclusivamente o risco PERF-001: impedir que o Kanban do Help
Desk carregue todos os chamados filtrados e suas relações na memória da API e
do navegador. Exportações, limites das demais listagens e refatorações
estruturais permanecem nas FIX-023, FIX-024 e FIX-025.

## Diagnóstico

O endpoint `GET /api/it/tickets?kanban=1` aceitava `per_page`, mas o ramo do
Kanban ignorava o parâmetro e encerrava a consulta com `get()`. Em seguida,
transformava toda a coleção para acrescentar as transições disponíveis e a
devolvia em uma única resposta.

Na interface, os chamados recebidos eram mantidos em uma única lista e então
distribuídos entre as sete colunas de status. Assim, o consumo de memória, o
tempo de serialização, a transferência e a renderização cresciam com todo o
resultado filtrado.

## Correção aplicada

- a API passou a usar `paginate()` antes de materializar os chamados;
- o tamanho padrão do Kanban é 25 e o limite validado permanece em 100;
- a resposta contém página atual, última página, tamanho, total, intervalo e
  links de primeira, última, anterior e próxima página;
- as transições disponíveis continuam sendo calculadas para cada chamado da
  página entregue;
- o frontend possui estado de paginação próprio para o Kanban, separado da
  tabela de chamados;
- a tela permite selecionar 15, 25, 50 ou 100 cartões por página;
- mudança de filtro ou tamanho retorna à primeira página;
- cada resposta substitui a lista anterior, sem acumular cartões em memória;
- as sete colunas de status foram preservadas e a grade passou a declarar sete
  colunas também no CSS.

## Testes criados e ajustados

O teste de integração Laravel cria 180 chamados fictícios e solicita a página
3 com 25 registros. Ele confirma:

- total 180, oito páginas e intervalo 51–75;
- exatamente 25 chamados na resposta;
- links anterior e próximo coerentes;
- manutenção das transições permitidas em cada cartão.

O teste Node `kanban-pagination.test.js` verifica o contrato estático da API e
da tela: paginação com padrão 25 e teto 100, presença de metadados/links,
controle visual e ausência de acumulação via `push`.

## Resultados

| Verificação | Resultado |
|---|---:|
| Testes Laravel filtrados por Kanban | 2 aprovados, 14 asserções |
| Teste frontend da paginação | 3 aprovados, 0 falhas |
| Regressão frontend acumulada | 34 aprovados, 0 falhas |
| Regressão Laravel | 168 testes, 1.022 asserções |
| Pint nos arquivos PHP alterados | aprovado |
| Build Quasar SPA | aprovado; 39 arquivos JS, 667,17 KB |

Os testes Laravel utilizaram o ambiente isolado da suíte. Nenhuma migration foi
executada e nenhum dado do MySQL real foi lido ou modificado.

## Riscos residuais

- a paginação é global para o resultado filtrado. Portanto, uma página pode
  deixar algumas colunas de status vazias mesmo que existam chamados daquele
  status em outras páginas; o filtro por status permite navegar um fluxo
  específico;
- paginação independente por coluna exigiria outro contrato de API e maior
  complexidade de estado, podendo ser avaliada futuramente se a operação
  demonstrar essa necessidade;
- teste de carga e medição de latência em volume de produção ainda são
  recomendados em homologação;
- exportações grandes não fazem parte desta correção e permanecem na FIX-023.

## Conclusão

A FIX-022 está **VALIDADA**. O Kanban não carrega mais toda a coleção filtrada:
a consulta e a interface trabalham com uma página limitada, preservando
filtros, relações, transições de estado e o fluxo existente. A FIX-023 não foi
iniciada.
