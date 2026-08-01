# Plano de Implementação — Spec 9: Gestão de Pesquisas/Assuntos e Filtragem do Grafo Por Tema

## 1. Migração de Banco de Dados PostgreSQL
- Criar migration CodeIgniter `2026-08-01-000001_CreateResearchProjectsTables.php`.
- Tabela `research_projects` (id, title, description, created_at, updated_at, created_by).
- Tabela `research_documents` (research_id, document_id, created_at).
- Índices em `research_documents(document_id)` e `research_documents(research_id)`.

## 2. Models & Services
- Criar `app/Models/ResearchProjectModel.php`:
  - Métodos CRUD básicos.
  - `attachDocument(int $researchId, int $documentId)`
  - `detachDocument(int $researchId, int $documentId)`
  - `getDocuments(int $researchId): array`
  - `getResearchesForDocument(int $documentId): array`
  - `syncDocuments(int $researchId, array $documentIds)`
- Atualizar `app/Models/EntityModel.php` e `app/Models/RelationshipModel.php`:
  - Adicionar métodos para filtragem de nós e arestas por `research_id`.

## 3. Controllers & Rotas
- Criar `app/Controllers/ResearchProjectController.php`:
  - `index()`: Listar pesquisas.
  - `store()`: Criar pesquisa.
  - `update(int $id)`: Editar pesquisa.
  - `delete(int $id)`: Remover pesquisa.
  - `manageDocuments(int $id)`: Tela de vincular documentos à pesquisa.
  - `saveDocuments(int $id)`: Salvar documentos vinculados.
- Atualizar `app/Controllers/GraphController.php`:
  - Aceitar parâmetro `research_id` via GET.
  - Filtrar entidades e relações caso `research_id` seja fornecido.
- Atualizar `app/Controllers/DocumentReviewController.php` e `BatchIngestController.php`:
  - Permitir selecionar a pesquisa durante o upload e vinculação de documentos.
- Atualizar `app/Config/Routes.php`:
  - Rotas sob `/researches`.

## 4. Frontend & Views
- Criar `app/Views/researches/index.php`: Lista de pesquisas com atalhos de filtro de grafo e gestão de documentos.
- Criar `app/Views/researches/form_modal.php` ou modal de criação/edição.
- Criar `app/Views/researches/documents.php`: Interface para vincular documentos a uma pesquisa.
- Atualizar `app/Views/graph/index.php`: Adicionar seletor dropdown de pesquisa no topo com recarregamento interativo do grafo Vis-Network.
- Atualizar `app/Views/documents/review_workspace.php` e `batch_ingest.php`: Adicionar seletor de pesquisa.
- Atualizar header de navegação (`app/Views/layout/main.php` ou parcial de navbar) com link para "Pesquisas".

## 5. Testes & Verificação
- Criar seeder/script de teste `ResearchProjectSeeder.php` com massa de dados (ex: "Baby de Andrade", "Revolução de 1932").
- Testar filtragem de grafo via browser e verificar se o grafo responde corretamente à escolha do assunto.
