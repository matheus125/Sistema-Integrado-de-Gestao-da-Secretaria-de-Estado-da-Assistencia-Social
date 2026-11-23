# Preparação para produção

## Estado atual

O ambiente local continua disponível em http://127.0.0.1:9000 com MySQL.
Nenhum domínio foi publicado nem serviço instalado no sistema operacional.
Os modelos abaixo assumem uma VM Linux com Nginx, PHP-FPM 8.4+ e MySQL 8.4.
Domínio, servidor, certificados, SMTP e destino externo de backups ainda precisam
ser definidos. Não copiar o `.env` local como configuração pronta de produção.

## Arquivos entregues

- `deploy/production.env.example`: variáveis de produção sem credenciais.
- `deploy/nginx/siges.conf.example`: HTTPS, SPA e API no mesmo domínio.
- `deploy/php/siges-pool.conf.example`: pool PHP-FPM, uploads até 50 MiB,
  limite de requisição de 60 segundos.
- `deploy/systemd/siges-queue.service`: filas `exports,notifications,default`.
- `deploy/systemd/siges-scheduler.service` e `.timer`: scheduler a cada minuto.
- `deploy/systemd/siges-backup.service` e `.timer`: backup diário às 03:00,
  no fuso do servidor, com janela de manutenção.
- `deploy/backup/run-backup.sh`: coordena manutenção e interrupção dos serviços.
- `scripts/backup-mysql.php`: dump MySQL, configuração, anexos e manifesto SHA-256;
  opção `--verify` restaura em um banco novo e compara todo o conteúdo.
- `php artisan siges:production-check`: diagnóstico de configuração, somente leitura.
- `scripts/browser-smoke.cjs`: navegação autenticada e downloads no navegador.
- `scripts/test-mysql-concurrency.php`: cria um schema MySQL descartável, executa
  os cenários multiprocesso da FIX-013 e remove o schema em qualquer resultado.

O frontend compilado usa `/api` por padrão; desenvolvimento mantém a porta 8000.
`QCLI_API_URL` permite substituir o endereço no build quando a arquitetura exigir.
O login não apresenta nem preenche credenciais de demonstração.
O tempo de reserva dos jobs passou para 180 segundos, acima do timeout de 120
segundos dos workers, conforme a documentação de filas do Laravel.
Exportações são geradas pela fila `exports`, armazenadas no disco privado e
expiram por padrão após 60 minutos. O scheduler remove arquivos expirados a
cada hora. Os limites padrão são 10.000 linhas para CSV, 5.000 para XLSX e 500
para PDF; ajuste-os somente após teste de capacidade em homologação.
Listagens autenticadas rejeitam `per_page` fora do intervalo de 1 até
`PAGINATION_MAX_PER_PAGE`, cujo padrão é 100.

## Publicação no servidor definido

1. Instalar PHP CLI/FPM da mesma versão compatível (8.4+), extensões exigidas
   pelo Composer, Nginx, MySQL client, `tar`, Composer e Node compatível com Quasar.
2. Colocar o projeto em `/var/www/siges`. Código deve pertencer ao usuário de
   deploy/root, sem escrita por `www-data`; permitir escrita somente em
   `storage` e `bootstrap/cache`. Não usar permissões 777.
3. Criar usuário MySQL próprio. Separar privilégios de operação (CRUD) dos
   privilégios de schema usados durante deploy; validar também os privilégios
   de leitura necessários ao mysqldump. Não utilizar `root` ou `dev` no serviço.
4. Preencher `.env` com base em `deploy/production.env.example`, preservando
   a APP_KEY do conjunto de dados importado. Proteger o arquivo e backups.
   Substituir senhas iniciais de usuários por credenciais individuais antes da
   abertura pública; o diagnóstico detecta as senhas conhecidas do seeder.
5. Configurar DNS e obter certificado TLS. Substituir `siges.example.org` e
   caminhos no modelo Nginx; instalar o pool FPM e conferir o socket.
6. Executar `composer install --no-dev --optimize-autoloader` e
   `composer check-platform-reqs --no-dev`. Executar `npm ci` no frontend e
   `npm run build`; publicar `dist/spa`. Dependências de build não precisam
   permanecer disponíveis ao servidor web após a compilação.
7. Restaurar o backup final do MySQL e os anexos após interromper as escritas
   na origem. Conferir hashes, contagens e vínculos. Não rodar `migrate:fresh`,
   `db:seed` ou `key:generate` sobre os dados migrados.
8. Conferir `php artisan migrate:status`, executar apenas migrations pendentes
   com `php artisan migrate --force`, e executar `php artisan config:cache`
   e `php artisan view:cache`. Validar Nginx com `nginx -t` e o pool com o
   comando de teste da versão PHP-FPM instalada antes de recarregar serviços.
9. Instalar os novos serviços systemd em `/etc/systemd/system`, executar
   `systemctl daemon-reload` e habilitar `siges-queue.service` e
   `siges-scheduler.timer`. Não habilitar simultaneamente os antigos workers
   `siges-notifications` de Supervisor/systemd nem um cron duplicado.
10. Configurar SMTP e integrações no servidor. Validar entregas apenas para
    destinatários autorizados; nenhum envio externo foi feito nesta preparação.
11. Executar `php artisan siges:production-check`, validar login, permissões,
    downloads e `/up` pelo HTTPS real e somente então liberar usuários.

Mudanças de código/configuração exigem reinício gracioso dos workers.
O servidor de desenvolvimento (`artisan serve` e `quasar dev`) não é o serviço
definitivo. O diagnóstico não substitui o teste de DNS, certificado ou serviços.

### Regressão de concorrência MySQL

Execute em desenvolvimento/homologação com permissão temporária para criar e
remover schemas, nunca com as credenciais limitadas do processo web:

```bash
php scripts/test-mysql-concurrency.php
```

O executor aceita como alvo destrutivo apenas o schema aleatório que ele próprio
cria com prefixo `siges_fix013_`. Ele não migra nem altera o banco configurado da
aplicação e remove o schema descartável em `finally`.

### Sessões e tokens Sanctum

Revise antes da publicação:

```dotenv
SANCTUM_TOKEN_EXPIRATION_MINUTES=480
SANCTUM_TOKEN_IDLE_TIMEOUT_MINUTES=120
SANCTUM_MAX_TOKENS_PER_USER=5
SANCTUM_PRUNE_EXPIRED_HOURS=24
```

O parâmetro `session_timeout_minutes` administrado no sistema prevalece sobre o
fallback de inatividade do ambiente. Mantenha o scheduler ativo para executar
`siges:prune-tokens` diariamente. Reduções de timeout afetam tokens existentes na
requisição seguinte; alterações de senha e bloqueio de usuário revogam suas
sessões. O Bearer token fica apenas em `sessionStorage`, portanto fechar a sessão
do navegador exige novo login. A migração futura para cookie `HttpOnly` depende
da homologação E2E descrita em `docs/auditoria/10-autenticacao-sessoes.md`.

### Verify Token do webhook WhatsApp

Defina `WHATSAPP_BUSINESS_VERIFY_TOKEN` com um valor exclusivo antes do primeiro
seeding e antes de habilitar a integração. Gere um token sem reutilizar senhas ou
outros segredos:

```bash
openssl rand -hex 32
```

O valor precisa ter pelo menos 32 caracteres. O seeder recusa valores fracos ou
padrão e não fornece fallback. Em instalações existentes, salve o novo token em
**Configurações > WhatsApp**, atualize o mesmo valor na configuração do webhook
da Meta e só então habilite a integração. `php artisan siges:production-check`
falha enquanto o token estiver ausente, fraco ou igual ao padrão legado. Não
confunda o Verify Token com `WHATSAPP_BUSINESS_APP_SECRET`, usado para validar a
assinatura dos eventos recebidos.

### Criptografia dos segredos de configuracao

Depois de aplicar a migration que adiciona `encrypted_value`, faça um backup
verificado antes de converter valores legados. A simulacao nao altera dados:

```bash
php artisan siges:encrypt-secrets
php artisan siges:encrypt-secrets --apply --backup=/caminho/backup-verificado.sql
```

O comando nunca imprime os valores, exige um arquivo de backup existente e
verifica o round-trip de cada segredo dentro de transacao. Preserve a `APP_KEY`
junto ao plano de recuperacao: sem ela, os valores e backups permanecem
ilegíveis. Para rotacionar a chave, mantenha temporariamente a chave anterior em
`APP_PREVIOUS_KEYS`, configure a nova `APP_KEY`, limpe o cache de configuracao e
execute `php artisan siges:encrypt-secrets --apply --rotate
--backup=/caminho/novo-backup-verificado.sql`. Remova `APP_PREVIOUS_KEYS` somente
depois da verificacao funcional e de um novo backup. Nunca execute `key:generate`
sobre uma base existente.

## Backups automáticos e recuperação

Criar `/var/backups/siges` com modo 0700 e proprietário `www-data` antes de
habilitar o timer. O wrapper usa exclusivamente os serviços novos deste pacote.
Se houver outro worker, cron, integração com escrita direta ou outro nó da
aplicação, adaptar a interrupção de todos eles antes de habilitar a rotina.

O backup diário coloca a aplicação em manutenção, para scheduler/workers,
aguarda requisições em andamento e copia banco, anexos e configuração. Reabre
a aplicação e retoma somente os serviços anteriormente ativos, inclusive em
caso de erro tratado. Uma manutenção já existente faz a rotina recusar execução.
`Persistent=false` evita uma parada inesperada para compensar um horário perdido;
monitorar execução diária com `systemctl list-timers` e `journalctl -u siges-backup`.
Interrupções abruptas do host exigem conferir manutenção e serviços ao reiniciar.

Somente diretórios com `COMPLETE` representam execução bem-sucedida. O arquivo
`manifest.json` contém hashes e contagens; `env` contém segredos. Programar
cópia criptografada para armazenamento fora do servidor e definir retenção
(por exemplo, 7 diários e 4 semanais) com o responsável pela infraestrutura.
Não foi configurada exclusão automática nem cópia externa sem destino definido.

Para ensaiar restauração, numa janela com todos os escritores interrompidos:

```bash
php artisan down --retry=60
php scripts/backup-mysql.php --quiesced --verify
php artisan up
```

Conferir o resultado do backup e sempre reabrir a aplicação após falha tratada.
`--verify` requer permissão para criar banco: gera `siges_restore_*`, restaura
SQL e arquivos em destinos separados e compara conteúdos. Não sobrescreve o
banco ativo; deixa a cópia disponível para inspeção. A implementação atual
calcula hashes em memória e é adequada ao volume local; reavaliar para bancos
grandes. A rotina cobre as tabelas atuais, sem stored routines/events externos.

Para ensaios descartáveis, `--isolated-test` exige `APP_ENV=testing`, um banco
com prefixo `siges_fix008_`, `--verify` e `--cleanup-restore`. Esse modo não
copia `.env`, credenciais ou anexos locais, valida contagens/hashes, foreign
keys, migrations e `CHECK TABLE`, e remove o banco restaurado ao final. O banco
fonte de teste também deve ser removido pelo operador depois do ensaio.

No desastre real, restaurar para um banco vazio usando `mysql`, conferir hashes
do manifesto, restaurar `storage/app` e APP_KEY correspondente, testar o sistema
isoladamente e depois apontar a aplicação ao banco recuperado. Novas gravações
posteriores ao backup exigem recuperação adicional; backup diário não implica
perda máxima zero. Nunca retornar ao SQLite antigo sem reconciliar alterações.

## Auditoria de telefones legados

Antes de considerar qualquer constraint de formato para telefones, execute:

```bash
php artisan siges:audit-phones --fail-on-invalid
```

O comando não exibe números nem altera dados. Corrija os registros apontados por
meio do fluxo administrativo e repita a auditoria até obter retorno aprovado.

Para placas, execute também:

```bash
php artisan siges:audit-plates --fail-on-issues
```

O comando informa apenas quantidades de placas inválidas, não normalizadas ou
duplicadas pela forma canônica. Nenhuma placa é exibida ou alterada.

Para CNH e RENAVAM, execute:

```bash
php artisan siges:audit-transport-identifiers --fail-on-issues
```

O comando valida o checksum dos RENAVAM e contabiliza, sem exibir valores, as
CNHs pendentes de classificação institucional. Não altere nem migre CNHs até que
seja definido se o campo representa registro nacional, número do espelho ou
formulário RENACH.

## Evidências locais em 10/09/2026

- 84 testes em MySQL isolado, 468 assertions, aprovados.
- Login e nove telas navegadas em Chromium; sem erros JS/HTTP no primeiro ensaio.
- Build de produção: nove telas sem erros JS/HTTP, 18 prévias de relatórios,
  downloads CSV/Excel/PDF e um anexo de chamado validados.
- Build SPA de produção gerado, sem endereço local da API embutido.
- Unidades systemd validadas por `systemd-analyze verify`.
- Backup real e restauração das 59 tabelas e arquivos comparados:
  `backups/mysql-20260910T133714Z-91d26e/restore-validation.json`.
- Evidências do navegador em `backups/browser-validation` e
  `backups/browser-production-validation`, ignoradas pelo Git por conterem
  capturas e relatórios com dados reais.
- Nginx/PHP-FPM/HTTPS ainda não foram instalados ou testados no servidor final.

Fontes: [Laravel Queues](https://laravel.com/framework/docs/12.x/queues),
[MySQL mysqldump](https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html).
