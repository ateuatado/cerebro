# Plano de Implementação — Spec 10: Suporte a Imagens JFIF

## 1. App / Services
- Update `app/Services/DocumentParserService.php`:
  - Adicionar `'jfif'` nas extensões suportadas pelo método `parseFile()`.
  - Atualizar os comparadores de MIME type / match em `rotatePage()` e `cropImageRegion()` para tratar `'image/jfif'` e `'image/pjpeg'` via `imagecreatefromjpeg()`.

## 2. App / Controllers
- Update `app/Controllers/BatchIngestController.php`:
  - Adicionar `'jfif'` na validação de extensão do `uploadItem()`.
- Update `app/Controllers/DocumentReviewController.php`:
  - Adicionar `'jfif'` na validação do `getTargetPageImage()`.
- Update `app/Controllers/ExtractionController.php`:
  - Atualizar mensagens de suporte de upload para mencionar arquivos JFIF.

## 3. Frontend & Views
- Update `app/Views/documents/batch.php`:
  - Incluir `.jfif` no atributo `accept` do input file e no texto descritivo.
- Update `public/assets/js/batch-ingest.js`:
  - Incluir `'jfif'` na lista de extensões permitidas no drag-and-drop / seleção.
- Update `app/Views/extraction/review.php`:
  - Incluir `'jfif'` no `accept` do upload e na renderização da tag `<img>` de fonte primária.

## 4. Testes e Verificação
- Criar `app/Database/Seeds/JfifSupportAcceptanceSeeder.php`.
- Testar a criação de imagem `.jfif` sintética e validar o fluxo de parsing, rotação, recorte e OCR.
