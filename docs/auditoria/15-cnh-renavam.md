# 15 — CNH e RENAVAM

## Escopo da FIX-011

Esta etapa investigou os dois identificadores existentes antes de impor formatos.
O resultado é intencionalmente assimétrico: a regra oficial do RENAVAM é
inequívoca e foi implementada; o campo genérico `drivers.cnh` permanece sem
validação semântica até a definição institucional de qual número do documento
deve ser armazenado.

## Inventário do sistema

| Campo | Banco/API | Formulário principal | Regra anterior |
|---|---|---|---|
| `drivers.cnh` | obrigatório, texto até 40, único | presente como “CNH” | obrigatório e único, sem formato semântico |
| `vehicles.renavam` | opcional, texto até 40, único | ausente | opcional e único, sem formato semântico |

A validade da CNH já é tratada separadamente em `cnh_expires_at`; esta etapa não
alterou esse fluxo.

## Regras oficiais verificadas

A [Resolução CONTRAN nº 1.020/2025](https://www.gov.br/transportes/pt-br/assuntos/transito/conteudo-contran/resolucoes/Resolucao10202025.pdf),
art. 10, distingue três identificadores no documento de habilitação:

- registro nacional: nove caracteres e dois dígitos verificadores, permanente
  para o condutor;
- número do espelho: nove caracteres e um dígito verificador, relativo ao
  documento expedido;
- formulário RENACH: onze caracteres, iniciados pela UF, com último dígito
  verificador facultativo.

Assim, o rótulo genérico “CNH” não permite concluir qual estrutura deve ser
validada. Aplicar uma regex ou algoritmo agora poderia rejeitar o dado correto ou
converter um identificador no tipo errado. A regra de CNH só será implementada
após o administrador escolher um dos três identificadores.

A [Portaria DENATRAN nº 27/2013](https://www.gov.br/transportes/pt-br/assuntos/transito/arquivos-senatran/portarias/2013/portaria0272013.pdf)
define o Código RENAVAM como numérico, permanente e composto por onze dígitos:
dez dígitos mais um dígito verificador calculado por módulo 11, peso 9. Essa
estrutura também é coerente com o serviço oficial de
[consulta de veículos da Senatran](https://www.gov.br/pt-br/servicos/consultar-dados-de-veiculo-na-base-renavam).

## Implementação

Para RENAVAM, a API agora:

- aceita somente onze dígitos;
- valida o dígito verificador com pesos `3, 2, 9, 8, 7, 6, 5, 4, 3, 2`;
- normaliza antes da regra de unicidade;
- devolve `422` para comprimento, caracteres ou dígito verificador inválidos;
- preserva o valor existente quando uma atualização é rejeitada.

Não foi adicionada máscara ou campo novo ao frontend, pois isso extrapolaria a
validação investigada nesta FIX. A autenticidade e a vinculação do código ao
veículo não podem ser provadas apenas pelo dígito verificador; quando necessário,
devem ser consultadas na base oficial da Senatran.

## Auditoria dos dados existentes

O comando abaixo é somente leitura e não exibe identificadores:

```bash
php artisan siges:audit-transport-identifiers --fail-on-issues
```

Resultado no MySQL configurado em 13/11/2026:

| Campo | Preenchidos | Válidos | Pendências |
|---|---:|---:|---:|
| CNH — identificador não definido | 1 | não avaliado | 1 |
| RENAVAM | 0 | 0 | 0 |

O retorno foi propositalmente diferente de zero por existir uma CNH que ainda
não pode ser classificada. Nenhum registro foi exibido, convertido ou alterado.

## Testes e regressão

Foram adicionados quatro testes com credenciais e dados fictícios:

- exemplos oficiais válidos de RENAVAM;
- comprimento, caracteres e dígito verificador inválidos;
- criação válida e atualização inválida sem escrita;
- auditoria de CNH ambígua sem alteração do valor.

Resultados:

- recorte Transporte: 17 testes e 124 asserções aprovados;
- suíte Laravel completa: 161 testes e 954 asserções aprovados;
- build Quasar SPA: aprovado.

## Estado e riscos residuais

A parte RENAVAM da FIX-011 está corrigida e coberta. A parte CNH está aguardando
definição institucional e não deve ser marcada como validada antes dessa decisão.

Permanecem:

- escolher qual identificador oficial o campo `drivers.cnh` representa;
- revisar o único valor legado somente após essa escolha, sem migração automática;
- decidir em etapa própria se RENAVAM deve aparecer no formulário principal;
- usar consulta Senatran quando for necessário comprovar existência/vínculo, pois
  o checksum valida apenas a estrutura.
