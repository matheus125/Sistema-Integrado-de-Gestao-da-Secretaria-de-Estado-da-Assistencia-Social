# 13 — Validação e normalização de telefones

## Escopo da FIX-009

A validação foi centralizada para os campos:

- Administração: `users.phone`;
- Transporte: `drivers.phone`;
- Help Desk: `it_tickets.requester_phone`;
- entrada autenticada do webhook WhatsApp que cria chamados.

Não foi adicionada máscara visual nesta etapa. O componente compartilhado e os
testes de digitação/colagem/cursor permanecem reservados à FIX-014.

## Regra adotada

São aceitos números brasileiros com DDD válido:

- fixo: DDD + oito dígitos, com assinante iniciado de 2 a 5;
- celular: DDD + nove dígitos, com assinante iniciado por 9;
- entrada com espaços, parênteses, hífen, ponto e prefixo brasileiro `+55`.

O backend rejeita letras, DDD inexistente, números estrangeiros e comprimentos
ou prefixos de assinante inválidos. Após a validação, persiste somente os 10 ou
11 dígitos nacionais. O identificador de conversa WhatsApp continua em E.164
(`55` + número nacional), preservando o vínculo das conversas.

## Auditoria dos dados existentes

O comando somente leitura abaixo exibe apenas contagens e pode falhar em rotinas
de readiness quando encontrar legado inválido:

```bash
php artisan siges:audit-phones --fail-on-invalid
```

Resultado no MySQL configurado em 13/11/2026:

| Área | Preenchidos | Inválidos | Válidos a normalizar |
|---|---:|---:|---:|
| Administração | 1 | 0 | 0 |
| Transporte | 1 | 0 | 0 |
| Help Desk | 5 | 1 | 0 |

Nenhum número ou credencial foi exibido e nenhum registro foi alterado. Como há
um chamado legado inválido, não foi criada constraint no banco nem realizada
correção automática. O administrador deve revisar esse chamado com o responsável
e repetir o comando antes de uma futura constraint.

## Testes

- formatos fixo, celular, formatado e E.164;
- normalização em Administração, Transporte e Help Desk;
- rejeição de letras, país estrangeiro, DDD, comprimento e prefixo inválidos;
- gravações rejeitadas não modificam o valor anterior;
- auditoria legada não altera nem expõe valores;
- evento WhatsApp assinado com número inválido não cria chamado ou mensagem;
- fluxo WhatsApp legítimo e idempotência permanecem aprovados.

Resultado: 7 cenários novos e 53 asserções; regressão completa com 151 testes e
888 asserções aprovadas, além do build Quasar SPA.
