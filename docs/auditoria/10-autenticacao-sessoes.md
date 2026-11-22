# 10 — Autenticação, sessões e tokens

## Decisão da FIX-006

O SIGES continua usando tokens Bearer do Sanctum. A troca para cookies de SPA
não foi feita nesta correção porque altera o modelo de autenticação, CSRF, CORS e
o fluxo de desenvolvimento em portas distintas. Essa migração exige ambiente de
homologação e testes E2E específicos de login, expiração, upload, download,
logout e proteção CSRF.

A arquitetura de produção prevista serve SPA e API no mesmo domínio, portanto o
modo SPA por cookie `HttpOnly`, `Secure` e `SameSite` do Sanctum é tecnicamente
viável e é a evolução recomendada. Antes da mudança será necessário configurar
`SANCTUM_STATEFUL_DOMAINS`, endpoint de CSRF, credenciais no `fetch`, domínio da
sessão e CORS, removendo o Bearer somente depois da regressão E2E.

## Política implementada

- validade absoluta padrão de 480 minutos;
- inatividade padrão de 120 minutos, consumindo o parâmetro
  `session_timeout_minutes` quando disponível;
- limite padrão de cinco tokens por usuário;
- `expires_at` gravado em cada novo token;
- rejeição de token de usuário inativo ou afastado;
- revogação ao trocar senha, inativar usuário, sair, encerrar todas as sessões ou
  revogar um dispositivo;
- listagem de dispositivos sem retornar hash ou token;
- limpeza diária de tokens expirados ou inativos, mantendo 24 horas de retenção;
- resposta `401` remove o estado de autenticação do navegador.

O token deixou de persistir em `localStorage` e passou a `sessionStorage`. Valores
legados são migrados uma única vez para a sessão atual e removidos do armazenamento
persistente. Isso reduz a janela de exposição após fechar o navegador, mas não
elimina o risco de XSS, pois JavaScript ainda precisa ler o Bearer token. A migração
futura para cookie `HttpOnly` é o controle definitivo para esse risco.

## Configuração

As variáveis são `SANCTUM_TOKEN_EXPIRATION_MINUTES`,
`SANCTUM_TOKEN_IDLE_TIMEOUT_MINUTES`, `SANCTUM_MAX_TOKENS_PER_USER` e
`SANCTUM_PRUNE_EXPIRED_HOURS`. O timeout configurado pela API aceita de 5 a 1440
minutos. A limpeza manual pode ser executada com:

```bash
php artisan siges:prune-tokens
```

O scheduler executa essa limpeza diariamente às 02:30. O comando não exibe
tokens, hashes ou dados pessoais.
