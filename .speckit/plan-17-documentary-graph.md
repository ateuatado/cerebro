# Plano 17

1. Projetar pares de entity_observations como arestas documentais, preservando
   identidade e filtro de pesquisa. Diferenciar estilo, tooltip e contadores.
   Testar montagem e filtros com navegador simulado.
2. Migration de pendências com FK documental e JSONB; persistir sugestões
   não resolvidas na mesma transação da extração. Exibir no detalhe da fonte.
   Testar normalização e renderização sem IA; preparar integração PostgreSQL.

Lint PHP/JS, diff-check e testes direcionados. pi-lens indisponível nesta sessão.
Testes PostgreSQL dependem de conexão dedicada; não usar o acervo para testes.

Revisão visual: filtrar somente a projeção de arestas nos grafos geral, por
pesquisa e dashboard; preservar nós, armazenamento e detalhes das fontes.
