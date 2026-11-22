# 17 — Concorrência em MySQL

## Escopo da FIX-013

Foram avaliadas operações simultâneas de estoque, agenda de transporte,
atribuição automática e numeração de chamados. A validação foi executada em
MySQL 8.4 com dois processos PHP independentes por cenário, usando exclusivamente
schemas descartáveis com prefixo `siges_fix013_`.

## Diagnóstico

| Cenário | Situação encontrada | Risco antes da correção |
|---|---|---|
| baixa de estoque | transação e `lockForUpdate` no produto já existentes | desenho seguro, ainda sem prova concorrente |
| agenda de veículo/motorista | consulta de conflito seguida de `create` fora de transação | duas requisições simultâneas podiam reservar a mesma janela |
| atribuição automática | cálculo da menor carga dentro da transação, mas sem lock comum | dois chamados podiam selecionar o mesmo técnico com carga antiga |
| numeração de chamados | chave primária `AUTO_INCREMENT` do MySQL | precisava de prova de unicidade sob criação simultânea |

## Correções

O fluxo de Transporte agora:

- executa validação de disponibilidade e criação na mesma transação;
- bloqueia veículo e motorista em ordem estável antes de consultar conflitos;
- bloqueia a solicitação antes de atualização ou transição de estado;
- aplica os mesmos locks durante aprovação e início da viagem;
- permite até três tentativas transacionais em caso de deadlock detectado pelo
  Laravel/MySQL.

A atribuição automática agora exige uma transação ativa e bloqueia a linha do
setor `gerencia-de-informatica` antes de recalcular a carga. Assim, a criação
seguinte somente escolhe o técnico depois do commit da anterior. A consulta de
recomendações permanece somente leitura e não adquire lock.

Não foi criada sequência paralela para chamados: o ID autoincremental continua
sendo a numeração canônica, evitando duplicar mecanismos de numeração.

## Teste multiprocesso

O comando reproduz o ensaio completo:

```bash
php scripts/test-mysql-concurrency.php
```

O script:

1. gera um nome aleatório validado com prefixo `siges_fix013_`;
2. cria e migra o schema vazio;
3. inicia dois processos independentes para cada operação;
4. executa `MysqlConcurrencyTest`;
5. remove o schema em bloco `finally`, inclusive após falha.

Resultados em 14/11/2026:

- estoque inicial 10, duas saídas simultâneas de 7: uma aceita, uma recusada,
  saldo final 3 e apenas uma movimentação;
- duas reservas simultâneas do mesmo veículo/motorista/janela: uma aceita e uma
  recusada, apenas uma solicitação persistida;
- dois chamados simultâneos: ambos criados, IDs distintos, duas mensagens e dois
  históricos independentes;
- dois técnicos inicialmente empatados: um chamado atribuído a cada técnico;
- teste MySQL: 1 teste e 22 asserções aprovados;
- recorte funcional SQLite: 57 testes e 424 asserções aprovados;
- regressão Laravel completa: 166 testes e 997 asserções aprovados;
- build Quasar SPA: aprovado;
- zero schemas `siges_fix013_*` remanescentes após o ensaio.

## Segurança do ensaio

O banco `siges` não foi migrado, limpo ou modificado. O script não imprime
credenciais, recusa nomes fora do prefixo exclusivo e nunca usa o nome do banco
real como alvo de remoção. Nenhuma integração externa foi acionada.

## Riscos residuais

- a serialização por setor privilegia consistência sobre throughput durante o
  curto trecho de escolha do técnico; monitorar contenção em volume elevado;
- índices e tempos de lock devem ser monitorados após crescimento substancial da
  agenda;
- o teardown inicial revelou que o `down()` da migration
  `2026_08_18_170000_add_advanced_it_service_management` tenta remover um índice
  ainda necessário a uma foreign key no MySQL. O ensaio passou a descartar o
  schema inteiro, como previsto, e essa migration não foi alterada por estar fora
  do escopo da FIX-013;
- cenários de carga prolongada e observabilidade de deadlocks pertencem à etapa
  de monitoramento e homologação de produção.
