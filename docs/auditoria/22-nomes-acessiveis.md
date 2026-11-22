# 22 — Nomes acessíveis em botões com ícones

Data da validação: 22/11/2026.

## Escopo

A FIX-018 tratou exclusivamente os botões `QBtn` com ícone ou formato redondo
do frontend Quasar. A auditoria inicial encontrou 166 controles dessa categoria,
dos quais 96 não possuíam `aria-label`, `label` ou `title` explícito.

Os controles estavam distribuídos por nove arquivos Vue:

- layout principal;
- Administração;
- Auditoria;
- Dashboard;
- Help Desk;
- Manutenção Preventiva de TI;
- Login;
- Materiais e Patrimônio;
- Transporte.

## Implementação

Foram adicionados nomes acessíveis contextuais aos 96 controles identificados.
Os textos descrevem a ação efetiva, como pesquisar, fechar, editar, excluir,
anexar, atualizar, enviar mensagem e alterar status.

Quando o significado depende do estado ou do registro, o nome também é
dinâmico. Isso cobre, por exemplo, mostrar ou ocultar senha, remover um seguidor
e excluir um item específico de checklist.

Os tooltips existentes foram preservados, mas não são mais a única fonte de
identificação dos controles. Ícones, regras de exibição, eventos, layout e fluxo
funcional não foram alterados.

## Teste automatizado

Foi criada a suíte
`frontend/quasar/tests/accessible-icon-buttons.test.js` e o comando:

```bash
npm --prefix frontend/quasar run test:accessible-buttons
```

O teste percorre recursivamente os arquivos Vue e exige um nome explícito e não
vazio em cada `QBtn` que use `icon` ou `round`. A verificação também mantém um
limite mínimo do inventário para evitar aprovação acidental por redução de
escopo.

Resultados:

| Verificação | Resultado |
|---|---:|
| Inventário inicial | 166 controles; 96 sem nome explícito |
| Inventário após a correção | 166/166 nomeados; 0 pendências |
| Teste de nomes acessíveis | 1 aprovado, 0 falhas |
| Regressão Laravel completa | 168 testes, 1.015 asserções |
| Build Quasar SPA | aprovado; Quasar 2.35.4 |
| Sintaxe Node e integridade do diff | aprovadas |

Nenhuma chamada externa foi realizada. O backend, o schema e os dados do banco
não foram modificados por esta correção.

## Riscos residuais

- O teste desta etapa analisa os componentes no código-fonte; ele não substitui
  a inspeção da árvore de acessibilidade produzida pelo navegador.
- Componentes de terceiros, contraste, ordem de foco, uso apenas por teclado e
  leitor de tela precisam da auditoria dinâmica prevista na FIX-021.
- A cobertura E2E permanente em navegadores permanece planejada para a FIX-027.

## Conclusão

A FIX-018 está **VALIDADA**. Todos os 166 botões Quasar com ícone ou formato
redondo identificados no código possuem nome acessível explícito, e um teste de
regressão impede a reintrodução dessa falha no mesmo conjunto de componentes.
