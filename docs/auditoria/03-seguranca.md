# 03 — Segurança

Referências: OWASP Top 10, OWASP API Security Top 10, práticas Laravel/Sanctum e controles observáveis no projeto.

## Vulnerabilidades confirmadas

| ID | Vulnerabilidade | Severidade | Evidência | Impacto | Recomendação |
|---|---|---|---|---|---|
| SEC-001 | Webhook WhatsApp aceita POST sem assinatura | Alta | `WhatsappWebhookController.php:30-56`; ao habilitar a integração no SQLite isolado, payload anônimo criou 1 chamado | criação de chamados falsos e falsificação de status de entrega | validar `X-Hub-Signature-256` sobre o corpo bruto com App Secret antes de processar |
| SEC-002 | Login sem rate limiting | Alta | rota pública sem `throttle`; 10 tentativas inválidas consecutivas retornaram 422, nenhuma 429 | brute force e credential stuffing | RateLimiter por IP + e-mail, resposta 429, atraso progressivo e monitoramento |
| SEC-003 | Tokens Sanctum não expiram e timeout configurável é inerte | Média | `config/sanctum.php:53` é `null`; `session_timeout_minutes` não possui consumidor | token roubado permanece válido até logout/revogação manual | expiração, rotação, revogação por inatividade e limpeza periódica |
| SEC-004 | Segredos de integração armazenados sem criptografia | Alta | `SystemSetting` usa cast `array`; token WhatsApp fica em JSON comum | leitura direta em vazamento/backup do banco | cast `encrypted:array` ou cofre externo; rotação dos segredos existentes |
| SEC-005 | Verify token padrão previsível | Média | fallback e seeder usam valor fixo; teste de verificação retornou 200 | facilita configuração insegura e enumeração do webhook | gerar segredo forte por ambiente e bloquear readiness se permanecer padrão |

## Riscos potenciais confirmados por versão/configuração

| ID | Risco | Severidade | Evidência | Aplicabilidade |
|---|---|---|---|---|
| DEP-001 | Dependências PHP vulneráveis | Crítica/Alta | 42 advisories: 1 crítico, 15 altos, 15 médios, 4 baixos, 7 sem classificação | inclui Laravel 12.63, Dompdf 3.1.2, Guzzle, CommonMark, Flysystem, Excel/PhpSpreadsheet e Symfony; explorar depende do caminho usado |
| DEP-002 | Dependências JS vulneráveis | Crítica/Alta | 13 pacotes: 1 crítico, 9 altos | parte dos avisos Quasar é SSR/SSG e o projeto é SPA; prototype pollution e toolchain ainda exigem atualização |
| SEC-006 | Token Bearer em `localStorage` | Média | `auth-store.js:6-9,39-42` | aumenta impacto de qualquer XSS; nenhum `v-html` foi encontrado, reduzindo probabilidade atual |
| SEC-007 | Headers de segurança incompletos | Baixa | Nginx define nosniff e Referrer-Policy, mas não CSP, frame-ancestors/X-Frame-Options nem HSTS | clickjacking e menor defesa em profundidade |
| SEC-008 | Exportações PDF/planilhas sem limite explícito | Média | relatórios fazem `get()` e Dompdf/PhpSpreadsheet possuem avisos de DoS | usuário autorizado pode gerar consumo elevado com grande volume |

## Autenticação

- login, logout e `/auth/me` funcionaram;
- token revogado passou de 200 para 401 após logout;
- token inválido/ausente recebe 401 JSON;
- mensagem de credencial inválida não revela se o e-mail existe;
- não existe recuperação de senha na API/UI;
- não há MFA, política de tentativas, expiração ou gerenciamento de dispositivos;
- parâmetro `password_min_length` existe, mas o cadastro usa `min:8` fixo.

## Autorização e IDOR

Pontos positivos:

- middleware `auth:sanctum` em todas as rotas privadas;
- permissões por módulo e por ação nos controllers;
- policy de chamado cobre administrador, aprovador, responsável, autor e setor;
- anexos reutilizam autorização do recurso pai;
- testes validam escopo de patrimônio, chamado e transporte;
- mensagens internas são ocultadas de perfis sem acesso técnico;
- operações de escrita de configurações de TI exigem `informatica.editar`.

Limitações:

- somente uma Policy formal (`ItTicketPolicy`); os demais módulos repetem regras em controllers;
- testes IDOR não cobrem sistematicamente todos os endpoints de detalhe/download;
- escopo por setor é regra sensível e deve receber testes negativos por endpoint a cada evolução.

Nenhuma falha IDOR foi confirmada nos cenários executados.

## Entradas, SQLi, XSS e mass assignment

- Query Builder/Eloquent parametriza buscas; raw SQL usa expressões/colunas internas, sem entrada direta observada.
- Não foram encontrados `v-html`, `innerHTML`, `eval` ou desserialização insegura.
- Todos os models declaram `$fillable`; requests/controllers validam antes de `create/update`.
- Telefone e placa carecem de validação semântica e aceitaram valores inválidos.
- Descrição de chamado não possui limite máximo explícito.
- Não foi executado fuzzing ofensivo; SQLi/XSS permanecem avaliados estaticamente, não por exploração.

## Uploads

Pontos positivos:

- máximo de 50 MiB;
- lista MIME explícita;
- nome físico gerado pelo storage;
- disco local privado (`storage/app/private`);
- download autenticado/autorizado;
- remoção do arquivo e registro de auditoria.

Riscos:

- ZIP e documentos Office são aceitos sem varredura antimalware;
- não há validação simultânea de extensão e MIME;
- limite de 50 MiB pode ser alto para memória/tempo;
- não há quarentena nem política de retenção documentada.

## Dependências — ação necessária

Não foi feita atualização automática. Antes de produção, atualizar lockfiles em branch própria, executar novamente audits, 84 testes, build, E2E e testes de exportação. Avisos críticos não devem ser simplesmente ignorados; os específicos de SSR podem ser documentados como não aplicáveis somente após confirmar que nenhum modo SSR/SSG é publicado.

## Bloqueadores de produção

1. SEC-001 — webhook anônimo.
2. SEC-002 — login sem rate limiting.
3. DEP-001/DEP-002 — versões com avisos críticos/altos.
4. SEC-004 — segredos em claro no banco.
5. ambiente atual com debug, HTTP, cookie inseguro, usuário de banco genérico e senhas padrão.
