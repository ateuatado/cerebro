# Tarefas 20 — Produtos intelectuais

- [x] T20.1 — Criar Biblioteca pesquisável, cadastro estruturado de obras e seleção reutilizável pelo Caderno (US20.1).
- [x] T20.2 — Criar lista e cadastro de produtos com estrutura inicial independente por tipo (US20.2).
- [x] T20.3 — Implementar edição e ordenação de partes e gravação do conteúdo por parte (US20.2). Depende de T20.2.
- [x] T20.4 — Implementar aba Bibliografia e vínculos sem duplicação entre obras e produtos (US20.3). Depende de T20.1 e T20.2.
- [ ] T20.5 — Validar no navegador e PostgreSQL o percurso com tese e artigo compartilhando obra, a recuperação da escrita e a preservação do grafo. Depende de T20.3 e T20.4.

Um commit por tarefa, com seu ID. Rodar pi-lens antes de fechar cada tarefa.

### Aceite adicional da T20.1

A Biblioteca abre diretamente pelo menu, permite cadastrar e consultar uma obra sem criar produto e oferece a tela de detalhe usada pela T19.4.

- [x] T20.6 — Associar obras por parte, filtrar o combo e indicar o uso na bibliografia geral (US20.4). Depende de T20.3 e T20.4.

- [x] T20.7 — Vincular fontes e fichas por parte e mostrar a visão geral com retorno ao original (US20.5). Depende de T20.3 e T19.3.

Os itens marcados foram implementados e publicados. T20.5 teve 15 verificações aprovadas no PostgreSQL; falta o percurso completo no navegador. Ver validation-20-intellectual-products.md. pi-lens indisponível na última verificação.

### Retomada

- [ ] T20.8 — Próxima entrega: especificar e implementar citações no texto, ligadas a uma obra ou fonte, com localizador. Distinguir seleção de material de citação efetiva. Depende de T20.3, T20.6 e T20.7.
- [ ] T20.9 — Planejar referências formatadas a partir do material citado, com ABNT como primeira saída e indicação de metadados faltantes. Depende de T20.8.
- [ ] T20.10 — Planejar exportação do manuscrito na ordem das partes, com citações e referências. Depende de T20.9.
