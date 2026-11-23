# 24 — Responsividade e foco dos modais

Data da validação: 23/11/2026.

## Diagnóstico

Foram inventariados 27 componentes `QDialog` no layout, no componente de
calendários SLA e nas páginas de Administração, Auditoria, Help Desk,
Manutenção Preventiva, Materiais e Transporte.

Os cartões usavam larguras responsivas, mas somente o detalhe de chamado
possuía limite de altura específico no mobile. Além disso, ao percorrer os
controles com `Tab`, o foco podia alcançar temporariamente o `body` depois do
último elemento antes de retornar ao diálogo. Isso não atendia ao critério de
foco continuamente preso.

## Implementação

- todos os cartões `.dialog-card` agora respeitam largura e altura úteis da
  viewport, com fallback `vh`, suporte a `dvh` e rolagem vertical interna;
- em telas abaixo de 600 px, a margem útil foi reduzida para 8 px por lado e
  as áreas de ações podem quebrar linha;
- um boot file central intercepta `Tab`/`Shift+Tab` e mantém o ciclo no diálogo
  ativo, inclusive quando há um diálogo aninhado;
- o refoco nativo do Quasar foi preservado. Quando o acionador pertence a um
  menu que é desmontado, o foco retorna ao botão que abriu esse menu;
- foi adicionado o roteiro automatizado `scripts/modal-responsive-test.cjs` e
  o comando `npm --prefix frontend/quasar run test:modals`.

## Matriz automatizada

O teste abre os 27 modais com dados fictícios em SQLite isolado. Para cada
modal, valida 375 × 667 px, 768 × 900 px e 1440 × 1000 px, totalizando 81
combinações. Em cada execução são verificados:

- limites do cartão dentro da viewport;
- ausência de overflow horizontal no documento e de conteúdo cortado;
- existência de rolagem quando a altura excede a área útil;
- foco inicial dentro do diálogo;
- ciclo de 12 avanços com `Tab` sem fuga de foco;
- fechamento e restauração do foco ao acionador ou ao seu acionador lógico.

Resultado final: **27 modais, 81 combinações, 0 falhas e 0 erros de runtime**.

## Regressão

| Verificação | Resultado |
|---|---:|
| Teste funcional dos modais em Chrome headless | 27/27 aprovados |
| Viewports verificadas | 375 px, 768 px e 1440 px |
| Combinações modal/viewport | 81/81 aprovadas |
| Testes frontend acumulados | 31 aprovados, 0 falhas |
| Regressão Laravel em SQLite `:memory:` | 168 testes, 1.015 asserções |
| Build Quasar SPA | aprovado; 39 arquivos JS, 659,65 KB |
| Sintaxe Node e integridade do diff | aprovadas |

O banco MySQL real não foi acessado nem modificado. O roteiro funcional usou
somente um arquivo SQLite temporário, credenciais fictícias e servidores locais
isolados nas portas 8010/9010.

## Riscos residuais

- A execução do roteiro requer Playwright e Chromium informados pelo ambiente;
  a incorporação dessas dependências e do navegador ao projeto/CI permanece na
  FIX-027, para não antecipar atualização de pacotes nesta etapa.
- Axe-core, contraste, leitor de tela e a auditoria ampla de teclado pertencem
  à FIX-021 e não foram antecipados.
- Mudanças futuras que adicionem modais precisam incluí-los no inventário do
  roteiro funcional.

## Conclusão

A FIX-020 está **VALIDADA**. Todos os modais atuais possuem limite e rolagem
responsivos, mantêm o foco preso e restauram o foco após o fechamento nas três
larguras exigidas.
