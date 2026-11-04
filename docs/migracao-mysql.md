# Migração local para MySQL — 10/09/2026

Destino: MySQL 8.4.11, `127.0.0.1:3306`, banco `siges`, InnoDB,
`utf8mb4_unicode_ci`, conexão Laravel em UTC. Esta é a instância local;
a configuração de hospedagem de produção permanece uma atividade separada.

## Resultado

- 59 tabelas e 24 migrations aplicadas.
- 615 registros importados em 56 tabelas, incluindo usuários, hashes de senha,
  tokens, sessões, permissões, históricos, auditoria e configurações.
- Os 24 registros de migrations do MySQL foram mantidos. O registro de cache
  da origem foi excluído da transferência; cache e locks são reconstruídos.
- Comparação de conteúdo normalizado por tabela antes do commit, 99 chaves
  estrangeiras conferidas por consultas de órfãos e sequências de IDs verificadas.
- Anexo existente preservado no mesmo storage e validado por SHA-256.
- 81 testes existentes: 458 assertions; 3 regressões de compatibilidade:
  10 assertions. Todos passaram no MySQL isolado.
- Verificações de leitura dos dashboards, reincidências, avaliações e
  manutenção passaram usando os dados reais importados.
- `.env` passou a usar MySQL; `APP_KEY` foi preservada.

## Backups e relatório

Diretório: `backups/mysql-migration-20260910T043814Z/`, ignorado pelo Git
e protegido por permissões locais. Contém dados sensíveis; não publicar.

- `database.sqlite`: snapshot consistente pela API de backup SQLite.
- `env.before`: configuração anterior, incluindo a chave da aplicação.
- `attachments.tar.gz`: cópia do storage da aplicação.
- `attachments-manifest.json`: hashes dos arquivos.
- `mysql-before.json`: conteúdo do MySQL antes da importação.
- `validation.json`: contagens e hashes por tabela após a importação.

O SQLite original em `database/database.sqlite` foi mantido e comparado com
o snapshot antes da troca. Não foi executado `db:seed` no destino importado.

## Implementação e verificações

`scripts/import-sqlite-to-mysql.php` é específico desta migração local, não
um importador genérico. Exige modo de manutenção, snapshot e destino com
as contagens iniciais esperadas. Desativa FKs somente na própria conexão,
substitui dados iniciais em uma transação e verifica todas as FKs antes do
commit, inclusive referências circulares. Em caso de falha anterior ao commit,
reverte a transação. Não usa models, eventos ou notificações para importar.

`scripts/activate-local-mysql.php` configura a conexão após a validação.
Ambos recebem a senha pela variável temporária `SIGES_DB_PASSWORD`.
Não reexecutar a importação sobre um banco em uso.

`php scripts/verify-local-mysql.php` confere a conexão e os principais
dashboards por leitura. O cache de permissões pode ser reconstruído.

O banco `siges_migration_test_20260910` foi usado exclusivamente para testes.
Nunca executar testes com `RefreshDatabase` apontando para `siges`.
O `phpunit.xml` continua permitindo testes rápidos em SQLite em memória;
os testes MySQL executados nesta migração usaram variáveis de ambiente
apontando explicitamente para o banco isolado e cache em array.

## Recuperação

Antes de qualquer retorno, colocar a aplicação em manutenção e interromper
workers, scheduler e entradas externas. Fazer backup do MySQL atual.

Se ainda não houve gravações novas no MySQL, restaurar `env.before` como
`.env`, preservar/restaurar o snapshot SQLite e os anexos correspondentes,
limpar o cache de configuração e validar a conexão antes de reabrir.

Depois de novas gravações no MySQL, o snapshot SQLite está desatualizado.
É obrigatório reconciliar/exportar essas gravações antes de retornar;
trocar somente a conexão causaria perda de dados. Não usar `migrate:fresh`,
rollback de migrations ou seeders como procedimento de recuperação.
