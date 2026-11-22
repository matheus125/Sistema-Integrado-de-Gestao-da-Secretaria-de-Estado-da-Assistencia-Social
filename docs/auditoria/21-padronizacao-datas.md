# 21 — Padronização de datas e horas

Data da validação: 22/11/2026.

## Escopo

A FIX-017 padronizou a apresentação de datas e horas no frontend:

- data: `dd/mm/aaaa`;
- data e hora: `dd/mm/aaaa HH:mm`.

Os campos editáveis continuam usando os tipos nativos `date` e
`datetime-local`. Seus `v-model` permanecem, respectivamente, em
`aaaa-mm-dd` e `aaaa-mm-ddTHH:mm`, preservando o contrato ISO enviado às APIs e
armazenado pelo backend.

## Implementação

O composable existente `useDateFormat.js` foi corrigido e ampliado. Antes da
FIX-017, `formatDateTime` apenas substituía `T` por espaço e ainda apresentava
`aaaa-mm-dd HH:mm`.

O helper central agora:

- valida os componentes de data e hora antes de apresentar;
- formata datas civis sem usar conversão de fuso, evitando mudança indevida do
  dia em valores como vencimento, manutenção e solicitação;
- preserva data/hora sem offset exatamente como recebida;
- converte timestamps com `Z` ou offset para o fuso do navegador;
- aceita fuso explícito em testes para resultados determinísticos;
- retorna `-` para valor ausente ou inválido.

O helper foi aplicado ou consolidado em:

- movimentações de estoque e patrimônio;
- motoristas, solicitações e manutenções de Transporte;
- chamados e prazos de SLA;
- manutenção preventiva;
- auditoria e sessões;
- prévia dinâmica de relatórios.

A prévia de relatórios identifica valores ISO de data/data-hora e os formata
somente na camada visual. O payload recebido continua inalterado.

## Testes

Foi adicionada a suíte `frontend/quasar/tests/date-format.test.js` e o comando:

```bash
npm --prefix frontend/quasar run test:date-format
```

Resultados:

| Verificação | Resultado |
|---|---:|
| Helper e integração frontend | 6 testes aprovados, 0 falhas |
| Regressão Laravel completa | 168 testes, 1.015 asserções |
| Build Quasar SPA | aprovado; 37 arquivos JS, 646,26 KB |
| Sintaxe Node e integridade do diff | aprovadas |

Os testes cobrem data civil, data/hora local, timestamp UTC, conversão para
`America/Manaus`, valores inválidos, uso do helper nas telas e permanência dos
inputs ISO nos formulários.

## Riscos residuais

- O aspecto visual dos controles nativos de data durante a edição depende do
  navegador e do idioma do dispositivo, embora o valor do `v-model` seja ISO e
  as datas exibidas fora do input estejam padronizadas.
- A verificação em navegadores e dispositivos reais deverá integrar a futura
  suíte E2E da FIX-027.

## Conclusão

A FIX-017 está **VALIDADA**. Datas exibidas usam o padrão brasileiro, data/hora
usa minutos em formato de 24 horas e os payloads de formulários permanecem ISO.
