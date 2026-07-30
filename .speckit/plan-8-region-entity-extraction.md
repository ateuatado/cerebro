# Plano Técnico 8 — Extração de Entidades e Grafo a partir da Seleção de Região

## Arquitetura de Componentes

### 1. DocumentReviewController (`app/Controllers/DocumentReviewController.php`)
- `extractEntitiesFromRegion(int $id, int $page)`:
  - Recebe `x, y, width, height, canvas_w, canvas_h`.
  - Recorta a imagem da página via `DocumentParserService::cropImageRegion()`.
  - Envia a imagem recortada em Base64 para o `DeepSeekService` ou executa OCR + IA.
  - Retorna JSON contendo `{success: true, transcription: '...', entities: [...], relationships: [...]}`.
- `confirmRegionEntities(int $id)`:
  - Salva as entidades selecionadas na tabela `entities` com `status = 'hypothesis'` e adiciona as conexões na tabela `relationships`.

### 2. DeepSeekService / DocumentExtractionService
- `extractFromImageCrop(string $cropImagePath, int $docId): array`:
  - Utiliza prompt especializado em leitura de manuscritos cursivos portugueses de 1920-1930 e extração estruturada de entidades/relações em JSON.

### 3. Views & Modal de Aprovação
- Modal `#modalRegionEntities` na view `app/Views/documents/review_workspace.php`.
- Handlers AJAX em `public/assets/js/review-workspace.js`.
