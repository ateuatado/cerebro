# Lista de Tarefas — Spec 8: Extração de Entidades e Grafo a partir da Seleção de Região

- [ ] `T1`: Atualizar `DeepSeekService.php` e `DocumentExtractionService.php` com suporte a extração estruturada HTR + Entidades a partir de imagem recortada (Base64)
- [ ] `T2`: Adicionar os endpoints `extractEntitiesFromRegion` e `confirmRegionEntities` em `DocumentReviewController.php`
- [ ] `T3`: Registrar as rotas de API em `app/Config/Routes.php`
- [ ] `T4`: Atualizar `review_workspace.php` adicionando o botão **"✨ Extrair Entidades (IA)"** e a modal de aprovação de entidades
- [ ] `T5`: Atualizar `review-workspace.js` com a lógica de chamada de extração de região, renderização dos cards na modal e confirmação no Grafo
- [ ] `T6`: Criar e executar o `RegionEntityExtractionAcceptanceSeeder.php` validando a extração por região e gravação no PostgreSQL
