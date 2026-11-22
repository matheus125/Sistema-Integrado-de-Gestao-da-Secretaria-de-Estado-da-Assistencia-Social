# Gerência de Informática / Help Desk

## Arquitetura

O módulo usa Laravel 12, Sanctum e permissões Spatie no backend, com Quasar/Vue no frontend. Os chamados ficam em `it_tickets` e usam relacionamentos para categorias, equipes, SLA, patrimônio, mensagens, tarefas, checklists, avaliações, aprovações e incidentes.

As regras de negócio ficam principalmente em `app/Services/It`. Os controllers da API aplicam escopo por solicitante, setor, técnico e perfil de aprovação. O frontend principal está em `frontend/quasar/src/pages/ItHelpDeskPage.vue`.

## Fluxo do chamado

Os estados operacionais são `aberto`, `triagem`, `em_atendimento`, `aguardando_usuario`, `resolvido`, `encerrado` e `cancelado`. Transições inválidas são rejeitadas. A resolução exige resumo; o estado aguardando usuário exige justificativa; o cancelamento exige motivo e deixa histórico.

Mensagens públicas do solicitante usam `inbound`. Mensagens técnicas usam `internal` ou `outbound`. O endpoint público não permite que um técnico forje uma mensagem inbound. Mensagens internas são filtradas para solicitantes no detalhe e na timeline.

## SLA

Regras em `it_sla_rules` podem combinar prioridade, categoria, tipo e setor. A resolução escolhe a regra mais específica e usa fallback quando necessário. Calendários em `it_sla_calendars` consideram dias úteis, intervalos, feriados e exceções. Os prazos gravados no chamado são `first_response_due_at`, `start_due_at` e `sla_due_at`.

## API e permissões

As rotas ficam em `routes/api.php` sob o prefixo `/api/it`. A permissão base é `informatica.visualizar`; alterações exigem `informatica.editar`, aprovação exige `informatica.aprovar` e categorias usam permissões próprias `informatica.categorias.*`.

## Integrações

O WhatsApp recebe mensagens por `WhatsappTicketService` e envia respostas por `WhatsappOutboundService`. As tentativas ficam registradas em `whatsapp_message_logs`. O pipeline de notificações registra `it_notification_logs` e dispara o evento `ItTicketNotificationCreated`.

## Operação

O scheduler executa escalonamentos a cada cinco minutos e manutenção preventiva diariamente. Os testes do Help Desk estão em `tests/Feature/ItHelpDeskWorkflowTest.php`, `ItAdvancedOperationsTest.php`, `ItSlaCalendarTest.php` e `ItTicketToolsTest.php`.

## Cuidados de produção

- configurar fila e worker para jobs;
- configurar armazenamento privado para anexos;
- restringir MIME types e tamanho dos uploads;
- executar o scheduler do Laravel;
- revisar permissões por perfil;
- monitorar notificações e logs do WhatsApp;
- manter backups do banco e dos anexos.

## Relacionamentos e incidentes

Chamados relacionados usam `it_ticket_relationships` para duplicidade, dependência, causa, bloqueio e incidentes derivados. A mesclagem encerra o chamado duplicado e mantém o vínculo para auditoria. Incidentes principais podem refletir mensagens e mudanças de estado para os filhos dentro de uma transação, registrando histórico individual.

## Notificações

Eventos de atribuição, resposta, reabertura e SLA são persistidos em `it_notification_logs`. `ItTicketNotificationCreated` dispara o pipeline `MarkItNotificationPipeline`, que registra o processamento e permite que um worker ou canal externo seja conectado sem alterar os fluxos de chamados.

## Segurança e desempenho

O acesso é filtrado por perfil, setor, solicitante e técnico. Notas internas são filtradas no detalhe e na timeline. Uploads aceitam somente tipos MIME documentais e imagens definidos pelo controller. Métricas MTTA e MTTR usam agregação no banco; atribuição usa contagens agregadas por técnico para evitar consultas repetidas.

## Testes e manutenção

Execute `php artisan test --do-not-cache-result` antes de publicar. O build do frontend é executado com `npm --prefix frontend/quasar run build`. Seeders devem ser executados com `php artisan db:seed`; categorias, equipes, permissões e usuários usam operações idempotentes.

## Deploy

Em desenvolvimento, o projeto usa SQLite. Em produção, configure MySQL em `.env`, execute `php artisan migrate --force`, publique o frontend gerado em `frontend/quasar/dist/spa`, mantenha `storage` gravável e configure `php artisan queue:work` e `php artisan schedule:work` como serviços supervisionados. O endpoint do WhatsApp deve usar HTTPS, App Secret e Verify Token exclusivo com no mínimo 32 caracteres. Gere o token no ambiente, salve-o em **Configurações > Integrações** e use o mesmo valor no cadastro do webhook na Meta; a integração não pode ser habilitada com valor ausente, fraco ou padrão.

### SMTP e worker de notificações

No `.env` de produção, altere `MAIL_MAILER` para `smtp` e preencha `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_EHLO_DOMAIN`, `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME`. As credenciais ficam somente no ambiente do servidor.

As notificações do Help Desk são encaminhadas para a fila `notifications`. Após alterar o `.env`, execute `php artisan config:clear` (ou `php artisan config:cache`) e inicie um worker dedicado:

```bash
php artisan queue:work database --queue=notifications --sleep=3 --tries=3 --timeout=120 --backoff=60 --max-time=3600
```

Use `deploy/supervisor/siges-notifications.conf` com Supervisor ou `deploy/systemd/siges-notifications.service` com systemd. O worker deve ser reiniciado após cada deploy para carregar o código novo. Falhas ficam em `failed_jobs` e podem ser inspecionadas com `php artisan queue:failed` e reprocessadas com `php artisan queue:retry all`.

Teste a configuração sem enviar uma mensagem real usando `php artisan tinker` e `Mail::raw('teste SMTP', fn ($mail) => $mail->to('destino@exemplo.gov.br')->subject('SIGES SMTP'));`. Em produção, execute o teste apenas com um destinatário autorizado.
