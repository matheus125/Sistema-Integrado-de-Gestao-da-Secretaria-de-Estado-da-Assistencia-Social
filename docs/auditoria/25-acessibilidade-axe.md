# 25 — Acessibilidade com axe e teclado

Data da validação: 23/11/2026.

## Escopo

A FIX-021 auditou o login e as nove rotas autenticadas em Chrome headless, nas
viewports desktop (1440 × 1000 px) e mobile (375 × 667 px). O login foi
verificado em desktop, totalizando 19 varreduras com axe-core 4.12.

As regras executadas correspondem às tags WCAG 2 A/AA e WCAG 2.1 A/AA. O
roteiro também percorre os controles por teclado e verifica se cada parada está
visível e possui indicação de foco.

## Diagnóstico inicial

A primeira execução encontrou 43 ocorrências, sendo 24 críticas ou sérias:

- nove ocorrências críticas de hierarquia ARIA na navegação lateral, porque
  itens com papel de botão eram filhos de um elemento com papel de lista;
- quinze ocorrências sérias de contraste em legendas, botões, cores de estado
  e ícone de estado vazio;
- dezenove ocorrências moderadas porque o `meta viewport` impedia zoom;
- um campo numérico dinâmico de Configurações sem nome acessível, identificado
  após a eliminação das causas globais;
- ausência de uma indicação global consistente de `:focus-visible`.

## Correções

- o viewport deixou de bloquear zoom e limitar escala máxima;
- a lista do drawer passou a expor navegação nomeada em vez de semântica de
  lista incompatível com os itens interativos;
- legendas receberam cor opaca com contraste suficiente;
- as cores `positive` e `warning` foram escurecidas para manter contraste com
  texto branco, inclusive em botões;
- botões temporariamente desabilitados por carregamento preservam contraste;
- o ícone decorativo de estado vazio passou de cinza 5 para cinza 7;
- controles dinâmicos de Configurações receberam nome acessível baseado no
  rótulo da configuração;
- controles focados por teclado receberam indicador duplo amarelo/preto,
  perceptível em fundos claros e escuros;
- foi criado o roteiro `scripts/accessibility-audit.cjs` e o comando
  `npm --prefix frontend/quasar run test:a11y`.

## Resultado final

| Verificação | Resultado |
|---|---:|
| Páginas/estados auditados | login + 9 rotas autenticadas |
| Varreduras axe | 19 |
| Violações críticas ou sérias | 0 |
| Violações axe totais | 0 |
| Paradas de teclado verificadas | 171 |
| Falhas de teclado/foco | 0 |
| Erros de runtime | 0 |
| Regressão de modais | 27 modais, 81/81 combinações |
| Testes frontend acumulados | 31 aprovados, 0 falhas |
| Regressão Laravel | 168 testes, 1.015 asserções |
| Build Quasar SPA | aprovado; 39 arquivos JS, 659,80 KB |

O banco MySQL real não foi acessado. A execução funcional usou SQLite
temporário, credenciais fictícias e servidores isolados nas portas 8010/9010.

## Riscos residuais

- Axe não substitui avaliação humana com leitores de tela, ampliação real,
  controle por voz e diferentes tecnologias assistivas.
- O roteiro depende de Playwright, Chromium e axe-core indicados pelo ambiente.
  A instalação permanente dessas dependências e a execução em CI permanecem na
  FIX-027, evitando antecipar atualizações de pacotes.
- Fluxos condicionais não exibidos pelos dados fictícios podem precisar de
  nova varredura quando forem adicionados ou alterados.

## Conclusão

A FIX-021 está **VALIDADA** conforme o critério acordado: nenhuma violação
crítica ou séria foi encontrada na matriz final. A execução final também ficou
sem violações moderadas ou menores detectadas pelo conjunto de regras adotado.
