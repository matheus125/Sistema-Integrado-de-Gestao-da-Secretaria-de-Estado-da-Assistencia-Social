# 11 — Cobertura CRUD de Administração e Materiais

## Escopo da FIX-007

Os testes usam SQLite em memória e credenciais fictícias. Nenhum dado do MySQL
real foi acessado ou alterado.

| Área | Cadastro | Consulta | Edição | Exclusão/desativação | Validação | Permissões/escopo |
|---|---|---|---|---|---|---|
| Usuários | aprovado | busca aprovada | aprovado | exclusão, afastamento e proteção do próprio usuário | campos, unique, setor, status e perfil | RBAC por ação |
| Setores | aprovado | busca/hierarquia | aprovado | remoção e bloqueio com vínculos | dependências, auto referência e ciclos | RBAC por ação |
| Perfis | aprovado | catálogo | sincronização de permissões | remoção e proteção de perfis padrão | nome único e permissões existentes | aprovação especial do Administrador Geral |
| Produtos | aprovado | busca | aprovado | soft delete | código único, quantidades e status | RBAC por ação |
| Estoque | entrada/saída | listagem | não aplicável: histórico imutável | não aplicável | saldo, tipo, quantidade, data e relações | setor forçado e filtrado |
| Patrimônios | aprovado | lista/detalhe | aprovado | soft delete | número único, relações, valor, data e status | isolamento por setor |
| Movimentações patrimoniais | transferência/manutenção/retorno/baixa | listagem | não aplicável: histórico imutável | não aplicável | destino obrigatório na transferência | RBAC e isolamento por setor |

## Defeitos corrigidos

1. A atualização de setor aceitava o próprio setor ou um descendente como pai,
   permitindo ciclos. A API agora retorna `422` para ambos os casos.
2. Transferência patrimonial sem `to_department_id` acessava uma chave ausente e
   retornava `500`. O destino passou a ser obrigatório e a resposta é `422`.

Concorrência real, foreign keys e restauração no MySQL isolado permanecem para a
FIX-008/FIX-013. Fluxos de navegador completos permanecem para a suíte Playwright
planejada na FIX-027.
