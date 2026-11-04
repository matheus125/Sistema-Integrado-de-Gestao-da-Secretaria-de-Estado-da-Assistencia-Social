# SLA por horário de atendimento — Parte 4

Na aba SLA do Help Desk, usuários com `informatica.editar` podem criar e editar calendários. A leitura exige `informatica.visualizar`. A interface informa o fuso definido em `config('app.timezone')` (UTC na configuração atual).

Cada calendário define dias de atendimento, início/fim no mesmo dia, intervalo opcional, feriados e exceções. O modo “todos os dias” inclui sábados e domingos, mas continua respeitando feriados e exceções.

Precedência: exceção explícita da data → feriado → dias habituais. Uma exceção sem atendimento fecha a data. Uma exceção com atendimento e sem horários utiliza o expediente habitual. Se houver horário especial, início e fim são obrigatórios; o intervalo da exceção substitui o habitual, podendo ficar vazio.

Primeira resposta, início e resolução usam o mesmo cálculo. Minutos fora das janelas não contam. Exemplo: abertura às 11h30, pausa 12h–13h e prazo de 60 minutos produz 13h30. Um prazo que termina exatamente no fechamento permanece naquele instante; aberturas no fechamento avançam ao próximo expediente. Um prazo zero é normalizado ao próximo instante de atendimento. Sem calendário, a regra conta tempo corrido.

A API rejeita expediente invertido, intervalo incompleto/fora do expediente, dias inválidos, datas duplicadas de exceção e calendário sem dias regulares (salvo modo todos os dias). O cálculo tem limite de busca de dez anos para falhar explicitamente com dados legados inválidos ou prazos excessivos.

Não é permitido desativar calendário usado por regra ativa nem associar calendário inativo a uma regra. Regras legadas com calendário inativo são ignoradas na resolução, permitindo fallback. Para desativar um calendário, altere ou desative suas regras primeiro.

Alterações de calendário não recalculam chamados em lote. Os prazos gravados permanecem até um novo cálculo pelo fluxo existente de abertura/edição do chamado. Não há migration nova: são utilizadas as tabelas e colunas existentes.

## API

- `GET /api/it/sla-calendars`: lista completa e fuso do sistema.
- `POST /api/it/sla-calendars`: cadastro.
- `PUT /api/it/sla-calendars/{id}`: alteração, inclusive parcial.
- `calendar_id` das regras SLA associa o calendário.

Uma exceção utiliza `date`, `working` e, opcionalmente, `starts_at`, `ends_at`, `break_starts_at`, `break_ends_at`. Datas seguem `YYYY-MM-DD`; horários seguem `HH:mm`.

## Validação

`tests/Feature/ItSlaCalendarTest.php` cobre prazos exatos, intervalos, fechamento, fins de semana, feriados, exceções, fuso, segundos, validação parcial, permissões, calendários inativos e os três prazos na criação real via API.

Executar: `php vendor/bin/phpunit --do-not-cache-result --filter 'ItSlaCalendarTest|ItHelpDeskWorkflowTest|AccessControlTest'` com o ambiente de testes SQLite em memória definido no `phpunit.xml`.

Frontend: `npm --prefix frontend/quasar run build`. O build valida compilação; não substitui homologação visual em navegador.
