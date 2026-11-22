# 14 — Validação e normalização de placas

## Escopo da FIX-010

A API de veículos agora aceita e normaliza os dois padrões brasileiros previstos
no plano:

- padrão antigo: entrada `ABC1234`, `abc-1234` ou `ABC 1234`, persistida como
  `ABC-1234`;
- padrão Mercosul: entrada `ABC1D23`, `abc1d23` ou `ABC-1D23`, persistida como
  `ABC1D23`.

Entradas fora das estruturas `LLL-NNNN` e `LLLNLNN` recebem `422`. A busca de
veículos também normaliza uma placa completa informada sem máscara.

## Unicidade canônica

A normalização ocorre antes da regra `unique`, evitando diferenças por caixa ou
separador. Uma segunda verificação compara a forma canônica com todos os veículos,
inclusive soft-deleted e registros legados ainda não normalizados. Assim,
`abc1234` e `ABC-1234`, ou `abc-1d23` e `ABC1D23`, são tratados como a mesma
placa. O índice único existente continua protegendo gravações concorrentes da
mesma forma canônica.

## Auditoria dos dados existentes

O comando somente leitura não exibe placas nem altera registros:

```bash
php artisan siges:audit-plates --fail-on-issues
```

Resultado no MySQL configurado em 13/11/2026:

| Preenchidas | Inválidas | A normalizar | Grupos duplicados |
|---:|---:|---:|---:|
| 2 | 0 | 0 | 0 |

Não foi necessária migração ou correção dos dados existentes.

## Testes

- formatos antigo e Mercosul, com caixa e separadores variados;
- rejeição de letras/dígitos em posições incorretas e comprimentos inválidos;
- persistência canônica dos dois padrões;
- bloqueio de duplicidade por caixa e formatação, inclusive contra legado;
- atualização inválida sem modificar a placa anterior;
- busca por placa antiga sem máscara;
- auditoria legada sem exposição ou alteração dos valores.

Resultado: 6 testes novos e 46 asserções; regressão completa com 157 testes e
934 asserções aprovadas, além do build Quasar SPA.

## Riscos residuais

- a FIX-015 implementou a máscara e os testes determinísticos de
  digitação/colagem/cursor; a interação em navegador real permanece para a
  cobertura E2E da FIX-027;
- CNH e RENAVAM permanecem para investigação institucional na FIX-011;
- nenhuma regra foi criada para placas estrangeiras, pois elas estão fora do
  escopo definido.
