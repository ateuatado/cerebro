# Plano Técnico 6 — Ingestão em 2 Estágios e Painel de Documentos Pendentes

## Arquitetura de Componentes

### 1. Serviço de Extração (`app/Services/DocumentExtractionService.php`)
- `extractFromDocument(int $documentId): array`
- `sanitizeTextForJson(string $text): string`
- `chunkText(string $text, int $chunkSize = 2800): array`
- `processChunk(string $chunk, string $docTitle): array`
- `persistGraphData(int $docId, array $entities, array $relationships, ?int $userId): array`

### 2. Controllers e Rotas
- Controller `app/Controllers/PendingExtractionController.php`:
  - `index()`: Carrega a view `documents/pending.php`.
  - `updateText()`: Atualiza o `conteudo_transcrito` via POST AJAX.
  - `extractSingle(int $id)`: Dispara extração individual via POST AJAX.
  - `extractBatch()`: Loop de extração para todos os pendentes via POST AJAX.
- Roteamento em `app/Config/Routes.php`:
  - `$routes->get('documentos/pendentes', 'PendingExtractionController::index');`
  - `$routes->post('api/documentos/pendentes/salvar-texto', 'PendingExtractionController::updateText');`
  - `$routes->post('api/documentos/pendentes/extrair/(:num)', 'PendingExtractionController::extractSingle/$1');`
  - `$routes->post('api/documentos/pendentes/processar-todos', 'PendingExtractionController::extractBatch');`

### 3. Modificações no BatchIngestController
- Atualiza `uploadItem()` para salvar `extraction_status => 'pending'` e tentar a extração via `DocumentExtractionService`.

### 4. Views e Assets Locais
- `app/Views/documents/pending.php`
- `public/assets/css/pending.css`
- `public/assets/js/pending.js`
- `app/Views/layout/navbar.php` (Adiciona link para pendentes)
