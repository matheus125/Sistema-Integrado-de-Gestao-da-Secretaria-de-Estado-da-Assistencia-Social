# 28 — Limite global de paginação

Data da validação: 23/11/2026.

## Escopo

A FIX-024 tratou PERF-003: impedir que usuários autenticados solicitem páginas
excessivas por meio de `per_page`. A correção abrange todas as listagens
paginadas atuais sem alterar seus tamanhos padrão. Refatorações estruturais
permanecem na FIX-025.

## Diagnóstico

Foram localizadas 16 chamadas a `paginate()` nos módulos Administração,
Transporte, Materiais/Patrimônio, Auditoria e Help Desk. Somente quatro
controllers declaravam `max:100`; os demais usavam diretamente
`$request->integer('per_page', ...)`, permitindo valores arbitrariamente altos.

## Correção aplicada

- criado `EnforcePaginationLimit` no grupo autenticado da API;
- a validação ocorre antes dos controllers e consultas paginadas;
- `per_page` deve ser um inteiro entre 1 e o teto global;
- o teto padrão é 100 e pode ser reduzido com
  `PAGINATION_MAX_PER_PAGE`;
- valores vazios, zero, negativos, decimais, notação exponencial, texto, arrays
  e números acima do teto retornam 422 com erro associado a `per_page`;
- o valor válido é normalizado para inteiro antes de chegar ao controller;
- regras locais existentes passaram a consultar a mesma configuração;
- a ausência de `per_page` preserva os padrões atuais de cada listagem;
- requisições não autenticadas continuam recebendo 401, sem antecipar a
  validação, e rotas públicas não receberam o middleware.

## Listagens cobertas

| Módulo | Listagens |
|---|---|
| Administração | usuários, setores |
| Transporte | veículos, motoristas, solicitações, manutenções |
| Materiais/Patrimônio | produtos, estoque, patrimônios, movimentações |
| Auditoria | logs |
| Help Desk | conhecimento, preventivas, aprovações, regras SLA, categorias, chamados |

## Testes

Os cinco cenários direcionados comprovam:

1. limite 100 é aceito e retorna no máximo 100 registros;
2. ausência do parâmetro mantém o padrão anterior de 15;
3. nove representações inválidas/extremas retornam 422 consistente;
4. as 16 listagens são bloqueadas acima do teto;
5. redução configurada para 25 é aplicada globalmente e autenticação/rotas
   públicas mantêm o contrato anterior.

## Resultados

| Verificação | Resultado |
|---|---:|
| Testes direcionados | 5 aprovados, 79 asserções |
| Regressão Laravel | 177 testes, 1.142 asserções |
| Regressão frontend | 37 testes, 0 falhas |
| Pint nos arquivos PHP alterados | aprovado |
| Build Quasar SPA | aprovado; 39 arquivos JS, 668,39 KB |

A suíte Laravel utilizou SQLite isolado. Esta correção não possui migration e
não modificou dados ou schema do MySQL real.

## Riscos residuais

- o teto limita linhas por resposta, mas consultas complexas ainda devem ser
  acompanhadas por métricas de latência e planos de execução em homologação;
- endpoints que retornam coleções completas sem `paginate()` não são convertidos
  automaticamente por esta regra e devem ser avaliados nas refatorações futuras;
- aumentar o teto em produção amplia consumo de banco, serialização, rede e
  navegador e deve ser precedido por teste de capacidade;
- novos endpoints públicos paginados precisarão optar explicitamente pelo
  middleware, pois a proteção atual está no grupo autenticado.

## Conclusão

A FIX-024 está **VALIDADA**. Nenhuma das 16 listagens autenticadas aceita
`per_page` extremo, e os padrões funcionais existentes foram preservados. A
FIX-025 não foi iniciada.
