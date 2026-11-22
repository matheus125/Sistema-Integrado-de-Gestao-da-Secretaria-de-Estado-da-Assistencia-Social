# 12 — Validação de restauração MySQL

## Escopo da FIX-008

Em 13/11/2026, a rotina de backup e restauração foi executada exclusivamente
com bancos descartáveis no MySQL 8.4.11 local. O banco configurado no `.env` não
foi alterado e nenhuma credencial foi gravada na evidência do ensaio.

O banco-fonte `siges_fix008_*` foi criado do zero, recebeu todas as migrations e
seeders e foi usado no ciclo `mysqldump` → banco restaurado `siges_restore_*`.
Os dois bancos descartáveis foram removidos ao final.

## Proteções adicionadas

O modo `--isolated-test` de `scripts/backup-mysql.php`:

- exige simultaneamente `--verify` e `--cleanup-restore`;
- aceita somente `APP_ENV=testing` e banco com prefixo `siges_fix008_`;
- não copia `.env`, credenciais nem anexos locais;
- compara contagem e SHA-256 normalizado de todas as tabelas;
- compara a definição das foreign keys da origem e da restauração;
- rejeita migrations pendentes ou histórico restaurado divergente;
- executa `CHECK TABLE ... QUICK` em todas as tabelas restauradas;
- remove o banco restaurado inclusive quando a validação falha.

O fluxo normal de produção permanece protegido por modo de manutenção,
`--quiesced` e interrupção prévia dos escritores. Nesse modo, configuração e
anexos continuam fazendo parte do backup operacional.

## Resultado do ensaio

| Verificação | Resultado |
|---|---:|
| Tabelas restauradas e comparadas | 59 |
| Registros comparados no ciclo final | 56 |
| Foreign keys comparadas | 99 |
| Migrations aplicadas | 26 |
| Migrations pendentes | 0 |
| `CHECK TABLE` | OK |
| Banco restaurado removido | Sim |
| Banco-fonte descartável removido | Sim |

O ensaio inicial após `migrate:fresh --seed` também restaurou e comparou os 402
registros sem divergências. O ciclo final foi repetido depois da suíte completa
em MySQL e confirmou novamente estrutura, dados e integridade.

## Regressão

- suíte Laravel em MySQL descartável: 144 testes e 835 asserções aprovados;
- suíte Laravel padrão em SQLite em memória: 144 testes e 835 asserções aprovados;
- build Quasar SPA: aprovado;
- Laravel Pint, sintaxe PHP e `git diff --check`: aprovados.

## Riscos residuais

- a verificação requer permissão temporária para criar e remover o banco de
  restauração;
- hashes de tabelas são calculados em memória e devem ser reavaliados para
  volumes muito maiores;
- stored routines e events externos não fazem parte do dump atual;
- retenção, cópia off-site e ensaio periódico com RPO/RTO pertencem à FIX-034;
- os testes específicos da FIX-013 foram executados posteriormente em MySQL
  descartável, com processos simultâneos e remoção integral do schema ao final.
