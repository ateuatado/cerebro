# Validação e atualização — Spec 18

## Verificações locais

- PHPUnit direcionado: 11 testes e 39 asserções aprovadas.
- Formulário nativo com CSRF; controlador com modelo simulado testa estado
  explícito e rejeição de opção inválida.
- Testes de projeção cobrem IDs numéricos/textuais, booleanos PostgreSQL,
  ocultação e restauração, preservação dos vizinhos e dos arrays originais.
- Sintaxe PHP e diff-check válidos. pi-lens indisponível.
- Teste opt-in PostgreSQL preparado, sem validação integrada nesta sessão.
  Nenhum dado do acervo foi alterado, nenhuma migration foi executada.

## Atualização no VPS

Na pasta do projeto, atualizar o código e conferir migrations pendentes:

```sh
git pull --ff-only origin master
php spark migrate:status
```

Depois de revisar pendências, executar `php spark migrate`. Esta versão exige
`2026-10-22-000003_AddEntityGraphVisibility`, que acrescenta apenas a coluna
booleana `graph_hidden` com default false. Não apaga entidades ou relações.

Em Entidades → O PARAFUSO, clicar em Ocultar no grafo. O formulário retorna ao
detalhe com confirmação e passa a oferecer Mostrar no grafo. A escolha vale
para todos os usuários do projeto. Ao abrir/recarregar os grafos global, por
pesquisa e dashboard, o nó e suas linhas não aparecem. Vizinhos isolados ficam
visíveis. Entidade, relações, atributos, fontes e listagens continuam acessíveis.

Repetir o envio do mesmo estado não alterna a escolha. Restaurar mostra o nó
novamente, respeitando os demais filtros exploratórios já existentes.

## Integração PostgreSQL

Com `database.tests` configurado em PostgreSQL dedicado, sem prefixo, ativar
`CEREBRO_PG_TESTS=1` e executar o filtro `EntityObservationPostgresTest` no
PHPUnit. O teste da Spec 18 confirma persistência, restauração e preservação de
atributos, confirmação, relações e evidências em schema de teste revertido.

## Correção T3 — Grafo completo no dashboard

O dashboard montava o grafo com todas as entidades, porém somente as oito
relações recentes. Agora consulta todas as arestas leves, mantendo as oito
somente na lista de recentes. Regressão com 12 relações verifica que todas
entram no grafo e que a lista permanece com oito. Sete testes, 32 asserções
aprovados, mais sintaxe PHP e diff-check. Sem nova migration ou reprocessamento.
O formulário informa explicitamente Visível nos grafos / Oculta nos grafos.
