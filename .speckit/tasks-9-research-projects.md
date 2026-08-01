# Lista de Tarefas — Spec 9: Gestão de Pesquisas/Assuntos e Filtragem do Grafo Por Tema

- [x] `T1`: Criar a migration `2026-08-01-000001_CreateResearchProjectsTables.php` com as tabelas `research_projects` e `research_documents`
- [x] `T2`: Executar a migration e criar o model `ResearchProjectModel.php` com métodos de vinculo documento-pesquisa
- [x] `T3`: Atualizar `RelationshipModel.php` e `EntityModel.php` para suporte a filtragem de grafo por `research_id`
- [x] `T4`: Criar o controller `ResearchProjectController.php` e registrar as rotas de pesquisas em `app/Config/Routes.php`
- [x] `T5`: Criar as views de gestão de pesquisas em `app/Views/researches/index.php` e `documents.php`
- [x] `T6`: Atualizar o header/navbar principal adicionando o menu "Pesquisas"
- [x] `T7`: Atualizar `GraphController.php` e `app/Views/graph/index.php` adicionando o seletor dropdown de pesquisas para filtragem dinâmica do Grafo Vis-Network
- [x] `T8`: Atualizar `DocumentReviewController.php` e `review_workspace.php` permitindo associar o documento a uma pesquisa durante a revisão
- [ ] `T9`: Executar testes e seed de verificação para validar a filtragem do grafo pela pesquisa "Baby de Andrade"
