# 20 — Máscara de moeda BRL

Data da validação: 22/11/2026.

## Escopo

A FIX-016 implementou um componente monetário reutilizável nos dois campos
financeiros editáveis encontrados no SIGES:

- valor do patrimônio, em Materiais;
- custo da manutenção de veículo, em Transporte.

Quantidades, quilometragens e configurações numéricas não foram tratadas como
moeda.

## Implementação frontend

O componente `BrlCurrencyInput`, baseado em `QInput`, apresenta o valor no
formato brasileiro e mantém no `v-model` uma string decimal canônica:

| Representação | Exemplo |
|---|---|
| Visual | `R$ 1.234,56` |
| `v-model` e payload JSON | `1234.56` |

O utilitário compartilhado realiza agrupamento, conversão entre dígitos e
centavos, colagem de valor BRL ou decimal, validação de faixa, exclusão junto a
separadores e preservação relativa do cursor. O processamento não utiliza
`Number` nem `parseFloat`; reais e centavos são manipulados como strings, sem
introduzir arredondamento binário no payload.

O campo de patrimônio admite dez dígitos inteiros, conforme seu
`DECIMAL(12,2)`. O custo de manutenção preserva o limite já existente de nove
dígitos inteiros.

A exibição do custo na tabela de manutenções também passou a usar o formatador
decimal compartilhado, removendo a conversão anterior para `Number`.

## Validação backend

As duas APIs agora combinam `numeric` com `decimal:0,2`, além dos limites de
faixa. Dessa forma, valores com mais de duas casas são rejeitados com `422`
antes de alcançar o banco. O valor de patrimônio também recebeu o limite
explícito compatível com a coluna, evitando erro de banco por estouro de faixa.

Os models já possuíam cast `decimal:2` e as tabelas já usavam
`DECIMAL(12,2)`; nenhuma migration foi necessária.

## Testes

Foi adicionada a suíte `frontend/quasar/tests/brl-currency.test.js` e o comando:

```bash
npm --prefix frontend/quasar run test:brl-currency
```

Resultados:

| Verificação | Resultado |
|---|---:|
| Componente/utilitário BRL | 7 testes aprovados, 0 falhas |
| Materiais direcionado | 10 testes, 95 asserções |
| Transporte direcionado | 4 testes, 35 asserções |
| Novos cenários backend | 2 testes, 18 asserções |
| Regressão Laravel completa | 168 testes, 1.015 asserções |
| Build Quasar SPA | aprovado; 37 arquivos JS, 645,12 KB |
| Integridade do diff | aprovada |

Os testes comprovam formatação, digitação progressiva, colagem BRL/decimal,
centavos, limites, cursor, exclusão, ausência de conversão de ponto flutuante,
payload canônico, persistência exata e rejeição de precisão/faixa inválida.

## Riscos residuais

- A interação em navegador e dispositivo móvel real deverá integrar a futura
  suíte E2E da FIX-027; a lógica foi validada deterministicamente e compilada
  no build de produção.
- Os limites permanecem diferentes por contrato: patrimônio aceita até
  `9999999999.99` e manutenção até `999999999.99`.

## Conclusão

A FIX-016 está **VALIDADA**. Os valores são exibidos em BRL e enviados às APIs
como strings decimais exatas de duas casas, sem conversão intermediária para
ponto flutuante.
