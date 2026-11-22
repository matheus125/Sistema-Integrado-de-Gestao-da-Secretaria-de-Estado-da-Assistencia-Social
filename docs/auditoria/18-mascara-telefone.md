# 18 — Máscara de telefone

Data da validação: 22/11/2026.

## Escopo

A FIX-014 implementou uma entrada compartilhada para telefones brasileiros
locais nos três formulários que já utilizam a validação da FIX-009:

- usuário da Administração (`phone`);
- motorista do Transporte (`phone`);
- solicitante do chamado de Help Desk (`requester_phone`).

O campo `whatsapp_business_phone` de Configurações não foi alterado. Ele possui
contrato próprio em E.164 e não deve receber automaticamente a máscara de
telefone nacional desta correção.

## Implementação

Foi criado o componente `BrazilianPhoneInput`, baseado em `QInput`, e um
utilitário compartilhado para normalização, formatação, validação e posição do
cursor. A apresentação aceita os dois formatos previstos:

- fixo: `(92) 3232-1234`;
- celular: `(92) 99999-1234`.

O componente mantém no `v-model` somente os 10 ou 11 dígitos nacionais. Assim,
o payload enviado à API permanece compatível com a normalização e a regra
`BrazilianPhone` do backend. Uma colagem com `+55`, espaços ou pontuação é
normalizada sem incluir o código do país no payload local.

O comportamento implementado cobre:

- formatação progressiva durante a digitação;
- colagem de números locais ou com o prefixo `+55`;
- exclusão por `Backspace` ou `Delete`, inclusive junto a separadores;
- restauração do cursor pela posição lógica entre os dígitos;
- edição de valores existentes;
- teclado numérico móvel por `type="tel"` e `inputmode="numeric"`;
- validação frontend equivalente à regra backend de DDD, fixo e celular.

Nenhuma credencial, migration, dado real ou configuração de ambiente foi
alterado.

## Testes

Foi adicionada a suíte Node `frontend/quasar/tests/brazilian-phone-mask.test.js`
e o comando `npm --prefix frontend/quasar run test:phone-mask`.

Resultados:

| Verificação | Resultado |
|---|---:|
| Máscara frontend | 6 testes aprovados, 0 falhas |
| Integração/validação API de telefone | 6 testes aprovados, 49 asserções |
| Regressão Laravel completa | 166 testes aprovados, 997 asserções |
| Build Quasar SPA | aprovado |

A suíte frontend comprova as transformações de digitação e colagem, a
normalização, as regras de validade, o mapeamento do cursor, a exclusão/edição e
o uso do componente nos três formulários. A suíte Laravel existente comprova a
integração da forma normalizada com Administração, Transporte e Help Desk.

## Riscos residuais

- O comportamento em navegador e dispositivo móvel real ainda deve integrar a
  futura cobertura E2E da FIX-027; a lógica de cursor foi validada nesta etapa
  de forma determinística e o componente foi compilado no build de produção.
- O telefone corporativo do WhatsApp requer uma solução específica para E.164,
  fora do contrato da FIX-014.
- O registro legado inválido identificado na FIX-009 continua preservado para
  correção administrativa, sem alteração automática de dados.

## Conclusão

A FIX-014 está **VALIDADA**. Os três telefones locais editáveis usam a mesma
máscara, enviam valores canônicos à API e mantêm a validação de segurança no
backend.
