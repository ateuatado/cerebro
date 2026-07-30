# Lista de Tarefas — Spec 6: Ingestão em 2 Estágios e Painel de Documentos Pendentes

- [ ] `T1`: Criar o serviço nativo `DocumentExtractionService.php` com sanitização de caracteres de controle, chunking por parágrafos e persistência no PostgreSQL
- [ ] `T2`: Atualizar `BatchIngestController.php` e `DocumentParserService.php` para salvar a transcrição completa em `conteudo_transcrito` e integrar o novo serviço de extração
- [ ] `T3`: Criar o `PendingExtractionController.php` e registrar as rotas de gerenciamento e extração manual em `Routes.php`
- [ ] `T4`: Criar a view `documents/pending.php` em Bootstrap 5 local com cards de estatísticas, lista de pendentes e modal de visualização/edição do texto bruto
- [ ] `T5`: Criar os arquivos de asset locais `public/assets/css/pending.css` e `public/assets/js/pending.js` para controle AJAX de extração e edição
- [ ] `T6`: Atualizar a barra de navegação principal (`app/Views/layout/navbar.php`) com o link "Pendentes de Extração"
- [ ] `T7`: Criar e executar o `ExtractionAcceptanceSeeder.php` validando os 6 critérios de aceite da Spec 6
