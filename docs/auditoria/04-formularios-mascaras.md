# 04 — Formulários e máscaras

## Inventário

Na auditoria original foram identificados 24 `QForm`, 149 ocorrências de
`QInput`, 88 de `QSelect`, 4 `QFile` e 24 diálogos nos arquivos de
página/componentes. Naquele momento não havia uso de `mask`, `unmasked-value`
ou componente compartilhado de máscara. As FIX-014, FIX-015 e FIX-016 passaram
a fornecer componentes próprios para telefones locais, placa e moeda BRL.

| Módulo/tela | Formulários | Campos relevantes | Máscara atual | Validação backend | Situação |
|---|---:|---|---|---|---|
| Login | 1 | e-mail, senha | nenhuma | required/email/string | adequada no formato básico |
| Administração | 3 | usuário, setor, perfil | nenhuma | limites, unique, roles | telefone inconsistente |
| Transporte | 5 | veículo, motorista, manutenção, solicitação, checklist | nenhuma | datas, números, fluxo | placa/telefone/CNH sem formato semântico |
| Materiais | 4 | produto, estoque, patrimônio, movimentação | nenhuma | numeric/min/unique/date | moeda sem formato BRL; limites só no backend |
| Help Desk | 9 | chamado, categoria, SLA, checklist, templates, automação, catálogo, status/mensagem | nenhuma | ampla validação inline | telefone e textos extensos inconsistentes |
| Manutenção preventiva | 1 | plano | nenhuma | intervalos numéricos e datas | aceitável, sem teste móvel do modal |
| SLA calendário | 1 | horários, feriados, exceções | inputs date/time | validação forte | funcional; formato visual depende do navegador |
| Relatórios/Auditoria | filtros | datas, busca, status | inputs date | validação de filtros | funcional |
| Configurações | dinâmico | número/texto/senha | nenhuma | valor genericamente castado | falta validação por chave |

## Máscaras ausentes ou inconsistentes

| Prioridade | Módulo | Tela | Campo | Problema confirmado | Sugestão |
|---|---|---|---|---|---|
| Alta | Administração | Usuário | Telefone | API aceitou `abc` | `(##) ####-####` / `(##) #####-####`, normalização e regex backend |
| Alta | Informática | Chamado | Telefone do solicitante | apenas string até 40 | mesmas máscaras e validação backend |
| Alta | Transporte | Motorista | Telefone | apenas string até 40 | mesmas máscaras e validação backend |
| Alta | Transporte | Veículo | Placa | API aceitou `x` | aceitar/normalizar `AAA-0000` e `AAA0A00` |
| Média | Transporte | Veículo | RENAVAM | backend permite string até 40; campo não está no formulário principal | definir regra institucional antes de mascarar |
| Média | Transporte | Motorista | CNH | sem formato/dígitos | validar quantidade e regra institucional |
| Média | Materiais | Patrimônio | Valor | `type=number`, sem BRL visual | máscara monetária BRL e conversão decimal segura |
| Média | Transporte | Manutenção | Custo | `type=number`, sem BRL visual | máscara monetária BRL |
| Média | Configurações | WhatsApp corporativo | Telefone | texto livre | formato E.164 no backend; máscara apenas visual auxiliar |

CPF, CNPJ e CEP não foram encontrados nos formulários atuais; não se recomenda inventar esses campos/máscaras.

Atualização de 13/11/2026: a FIX-009 implementou a validação e normalização
backend dos três campos de telefone acima. Em 22/11/2026, a FIX-014 adicionou o
componente compartilhado `BrazilianPhoneInput` aos formulários de usuário,
motorista e chamado, mantendo somente os dígitos nacionais no payload da API.
A tabela preserva o diagnóstico original para rastreabilidade.

A FIX-010 também implementou os formatos antigo e Mercosul, normalização e
unicidade canônica de placas. Em 22/11/2026, a FIX-015 adicionou o componente
`BrazilianVehiclePlateInput`, mantendo no payload `ABC-1234` para o padrão
antigo e `ABC1D23` para o Mercosul.

A FIX-011 implementou no backend a estrutura e o dígito verificador oficial do
RENAVAM. CNH permanece sem formato semântico porque o campo atual não informa se
representa registro nacional, número do espelho ou formulário RENACH; essa
definição institucional deve anteceder qualquer máscara ou validação.

Em 22/11/2026, a FIX-016 substituiu os inputs numéricos de valor patrimonial e
custo de manutenção pelo componente `BrlCurrencyInput`. A tela apresenta
`R$ 1.234,56`, enquanto o `v-model` e a API recebem `1234.56` como string
decimal de duas casas.

## Validação divergente

- Frontend aplica quase somente `required`; máximos e regras semânticas ficam no backend.
- Na auditoria original, nenhum `maxlength` foi encontrado nos componentes Vue,
  embora o backend tivesse vários `max`. A FIX-012 alinhou os limites dos campos
  editáveis principais e completou os limites backend ausentes.
- Configurações aceitam qualquer valor por chave e convertem número inválido para zero.
- A `description` do chamado era obrigatória, mas não possuía `max` no backend;
  a FIX-012 definiu 10.000 caracteres após conferir os dados existentes.
- Datas e horas usam inputs nativos ISO; o backend valida `date`/`date_format`,
  o que evita datas impossíveis. A FIX-017 preservou esse contrato e
  padronizou a apresentação fora dos inputs em `dd/mm/aaaa` e
  `dd/mm/aaaa HH:mm`.
- Quantidades, KM e custos possuem `min` no backend em vários fluxos, mas o frontend nem sempre espelha os limites.

## Comportamento de máscara

Na auditoria original, digitação, colagem, backspace, cursor e edição de valor
mascarado eram **NÃO APLICÁVEIS**, porque não havia máscara implementada. O
teste dinâmico demonstrou então que letras chegavam ao backend em telefone e
placa.

Após as FIX-009/FIX-010, letras e formatos inválidos passaram a ser recusados
pelo backend. A FIX-014 também passou a cobrir a interação mascarada de
telefone: digitação, colagem, exclusão junto a separadores, cursor por posição
lógica, edição e teclado móvel foram exercitados pela suíte Node. A FIX-015
aplicou a mesma estratégia à placa, incluindo a transição automática entre os
padrões antigo e Mercosul. A FIX-016 adicionou digitação e colagem monetárias,
centavos, agrupamento, cursor e exclusão sem conversão para ponto flutuante.
A FIX-017 centralizou a apresentação de datas/horas e manteve os valores ISO
dos formulários separados da formatação visual.

## Padrão recomendado

1. Criar componentes/composables compartilhados sobre `QInput`.
2. Usar `mask` e `unmasked-value` apenas como UX.
3. Normalizar no backend e validar o valor não formatado.
4. Manter formatos institucionais configuráveis para matrícula, processo e patrimônio.
5. Adicionar testes de digitação, colagem, edição, mobile e payload API.

## Acessibilidade e mensagens

- Campos possuem labels visíveis na maioria dos formulários.
- Na auditoria original, erros 422 mostravam apenas a primeira mensagem global.
  A FIX-019 passou a associar todas as mensagens aos respectivos campos,
  inclusive chaves aninhadas e múltiplas mensagens no mesmo campo.
- Na auditoria original, botões de fechar/editar/excluir icon-only frequentemente
  não tinham nome acessível. A FIX-018 corrigiu os 96 casos encontrados e passou
  a exigir nome explícito em teste automatizado.
- Mensagens de erro são majoritariamente genéricas (`Campo obrigatorio`).
