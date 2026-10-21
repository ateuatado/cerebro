# Spec 14 — Desempenho do Grafo

**Status**: implementado localmente; validação operacional pendente  
**Data**: 2026-10-21  
**Dependências**: Spec 1 (modelo de dados), Spec 9 (pesquisas)  

## Objetivo

Reduzir o custo de carregamento da tela do grafo sem alterar os dados nem a
rastreabilidade das entidades e relações.

## Escopo inicial

- Consultas específicas para o grafo, sem carregar JSONB e timestamps desnecessários.
- Preservar os métodos completos usados por entidades, dashboard e seeds.
- Manter filtros por pesquisa funcionando.

## Próxima etapa

Se o volume justificar, carregar vizinhanças do grafo sob demanda e limitar o
grafo global inicial. Essa etapa será medida separadamente.
