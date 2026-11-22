# 16 — Limites de campos

## Escopo da FIX-012

As validações Laravel foram comparadas com os formulários Quasar. O diagnóstico
confirmou que vários campos já possuíam `max` no backend, mas nenhum formulário
declarava `maxlength`. Também havia textos em colunas `TEXT` sem limite de
aplicação, inclusive a descrição do chamado.

Esta correção adiciona defesa nas duas camadas. O frontend orienta o usuário e
evita submissões acidentais; o backend continua sendo a fronteira obrigatória e
rejeita requisições diretas acima do limite com `422`.

## Compatibilidade com os dados existentes

Antes da alteração, foi executada uma consulta somente leitura com
`MAX(CHAR_LENGTH(...))`. Nenhum valor foi exibido ou modificado.

| Campo sem limite anterior | Maior valor existente | Novo limite |
|---|---:|---:|
| descrição do chamado | 32 | 10.000 |
| mensagem do chamado | 48 | 10.000 |
| roteiro da solicitação de veículo | 27 | 4.000 |
| observação da solicitação de veículo | 5 | 4.000 |
| observação de veículo | sem valores | 4.000 |
| observação de estoque | 6 | 4.000 |
| observação de movimentação patrimonial | 17 | 4.000 |
| descrição de categoria de chamado | 29 | 2.000 |
| descrição de tarefa | sem valores | 4.000 |

Os limites foram aplicados sem reduzir, converter ou truncar dados. Não foi
necessária migration porque as colunas existentes já comportam os valores.

## Matriz aplicada

| Grupo | Limites principais |
|---|---|
| chamados | descrição e mensagens 10.000; categoria 2.000; tarefa 4.000 |
| transporte | roteiro e observações gerais 4.000; manutenção/checklist 1.000 |
| materiais | observações 4.000; textos curtos preservam os limites de 30 a 255 já existentes |
| administração | nomes 120/160; e-mail 180; senha 255; telefone 25 |
| configurações | valores textuais dinâmicos 4.096 |
| base de conhecimento | conteúdo 50.000 |

Os demais campos curtos dos formulários passaram a espelhar exatamente os
limites Laravel já existentes. Datas, horários e números não receberam
`maxlength`, pois usam validações de tipo, faixa `min`/`max` ou listas fechadas.
Os editores JSON de automações representam arrays validados no backend e não
foram tratados como texto persistido.

## Experiência no frontend

Foi criado um validador reutilizável que retorna a mensagem
`Use no máximo N caracteres`. Os `QInput` editáveis agora declaram `maxlength` e
regra correspondente; textos longos exibem contador. Desde a FIX-019, os erros
`422` são associados diretamente aos campos e preservam todas as mensagens
retornadas pelo Laravel.

## Testes

`FieldLengthValidationTest` cobre:

- aceitação da descrição no limite exato de 10.000 caracteres;
- rejeição com 10.001 caracteres sem alterar ou criar chamado;
- mensagens e tarefas excessivas sem escrita;
- limites de Transporte e categorias;
- configuração dinâmica excessiva sem alteração;
- presença do contrato de limite nos formulários Vue principais.

Resultado direcionado: 5 testes e 43 asserções aprovados. A regressão completa
encerrou com 166 testes e 997 asserções aprovados; o build Quasar SPA também foi
aprovado.

## Riscos residuais

- `maxlength` melhora a experiência, mas não substitui a validação backend;
- conteúdo estruturado de automações possui validação por array e tipo, mas
  limites de quantidade/profundidade devem ser avaliados junto da FIX-023/FIX-024;
- a associação visual dos erros `422` foi concluída pela FIX-019 e possui teste
  de cobertura permanente;
- alterações futuras em limites Laravel devem atualizar simultaneamente o
  formulário correspondente e seus testes.
