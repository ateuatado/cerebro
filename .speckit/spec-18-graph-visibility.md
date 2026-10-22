# Spec 18 — Ocultar e mostrar entidade no grafo

Data: 2026-10-22. Constituição I–VI consultada.

Na página de detalhe de qualquer entidade, oferecer Ocultar no grafo / Mostrar
no grafo. Preferência persistente e compartilhada no projeto único, independente
do navegador. Usuários autenticados podem alterar como já editam entidades.
POST protegido pelo filtro de autenticação e CSRF, com estado desejado explícito
para que reenvios não invertam a escolha.

A ação altera exclusivamente graph_hidden (booleano, default false). Não altera
atributos, classificação, confirmação, relações, fontes ou identidade. Ocultar
remove nó e linhas incidentes dos grafos global, por pesquisa e dashboard;
entidades vizinhas continuam visíveis, mesmo isoladas. Listagem e detalhe não
são filtrados, permitindo restaurar a visibilidade. Contadores do dashboard
continuam contando registros; contadores do grafo contam a projeção visível.

Sem exclusão, nova inferência ou promoção a fato. Sem nome de periódico fixado
no código. Não executar limpeza nem atualizar PHP ou migrations anteriores.

Aceite: botão reversível no detalhe; reenvio idempotente; relações e fontes
preservadas; estado reconhecido em booleanos PostgreSQL; sem pontas invisíveis;
filtrar visibilidade antes de montar dados para todos os grafos.
