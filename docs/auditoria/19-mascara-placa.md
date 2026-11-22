# 19 — Máscara de placa

Data da validação: 22/11/2026.

## Escopo

A FIX-015 implementou um componente compartilhado para o campo de placa do
cadastro de veículos, mantendo o contrato de normalização e validação definido
no backend pela FIX-010.

Os dois padrões brasileiros previstos são suportados:

- padrão antigo: `ABC-1234`;
- padrão Mercosul: `ABC1D23`.

Placas estrangeiras permanecem fora do escopo definido no plano.

## Implementação

O componente `BrazilianVehiclePlateInput`, baseado em `QInput`, utiliza um
utilitário compartilhado para:

- converter letras para maiúsculas;
- remover caracteres que não façam parte da placa;
- limitar o conteúdo aos sete caracteres significativos;
- inserir o hífen no padrão antigo;
- remover automaticamente o hífen quando a quinta posição identifica o padrão
  Mercosul;
- validar as posições de letras e números nos dois padrões;
- preservar o cursor pela posição lógica entre os caracteres;
- tratar `Backspace` e `Delete` junto ao hífen;
- editar valores canônicos previamente carregados pela API.

O `v-model` e, consequentemente, o payload da API recebem a forma canônica
visual: `ABC-1234` para placa antiga e `ABC1D23` para Mercosul. O backend
continua sendo a validação definitiva e preserva a unicidade canônica.

Nenhuma migration, configuração de ambiente ou dado real foi alterado.

## Testes

Foi adicionada a suíte
`frontend/quasar/tests/brazilian-vehicle-plate-mask.test.js` e o comando:

```bash
npm --prefix frontend/quasar run test:plate-mask
```

Resultados:

| Verificação | Resultado |
|---|---:|
| Máscara frontend | 6 testes aprovados, 0 falhas |
| Validação e integração API de placas | 6 testes aprovados, 46 asserções |
| Regressão Laravel completa | 166 testes aprovados, 997 asserções |
| Build Quasar SPA | aprovado; 36 arquivos JS, 642,42 KB |
| Integridade do diff | aprovada |

A suíte frontend cobre digitação progressiva, transição entre os padrões,
colagem, caixa, separadores, validação posicional, cursor, exclusão, edição e o
uso efetivo do componente no formulário de veículo. A suíte Laravel cobre a
persistência canônica, rejeição segura, atualização, busca e duplicidade.

## Riscos residuais

- A interação em navegador e dispositivo real deverá integrar a futura suíte
  E2E da FIX-027; a lógica de cursor foi validada deterministicamente nesta
  etapa e compilada no build de produção.
- A regra não contempla placas estrangeiras, conforme o escopo aprovado.

## Conclusão

A FIX-015 está **VALIDADA**. O cadastro de veículos aceita os padrões antigo e
Mercosul com formatação, edição e payload canônico alinhados ao backend.
