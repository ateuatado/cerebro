# Lista de Tarefas — Spec 7: Workspace Interativo de Transcrição Histórica

- [ ] `T1`: Atualizar `DocumentParserService.php` com suporte a renderização paginada em alta resolução, rotação de imagens em PHP GD e recorte de região (Crop)
- [ ] `T2`: Criar `DocumentReviewController.php` com métodos de renderização de imagem de página, rotação, extração por região com IA e salvamento paginado
- [ ] `T3`: Registrar as rotas do workspace de revisão e API de páginas em `app/Config/Routes.php`
- [ ] `T4`: Criar a view `app/Views/documents/review_workspace.php` com layout divido em 2 colunas, barra de ferramentas de rotação/crop e editor paginado
- [ ] `T5`: Criar os assets locais `public/assets/css/review-workspace.css` e `public/assets/js/review-workspace.js` com suporte a desenho de caixa de seleção (crop) e rotação
- [ ] `T6`: Criar e executar o `ReviewWorkspaceAcceptanceSeeder.php` validando os 5 critérios de aceite da Spec 7
