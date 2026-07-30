# Plano Técnico 7 — Workspace Interativo de Transcrição Histórica

## Arquitetura de Componentes

### 1. DocumentReviewController (`app/Controllers/DocumentReviewController.php`)
- `review(int $id)`: Carrega o workspace de transcrição paginada para o documento `$id`.
- `getPageImage(int $id, int $page)`: Transmite a imagem renderizada da página `$page`.
- `rotatePage(int $id, int $page)`: Processa a rotação da imagem da página com PHP GD/PyMuPDF e atualiza o repositório.
- `extractRegion(int $id, int $page)`: Processa o recorte pelas coordenadas `[x, y, w, h]` e aciona o `DeepSeekService` para leitura HTR.
- `savePageText(int $id, int $page)`: Salva a transcrição da página no atributo `conteudo_transcrito` do documento.

### 2. Ampliação do DocumentParserService (`app/Services/DocumentParserService.php`)
- `ensurePdfPagesRendered(string $pdfPath, string $outputDir): array`: Garante que cada página do PDF está renderizada como JPEG em alta resolução (150 DPI).
- `rotateImageFile(string $imgPath, int $degrees): bool`: Executa a rotação física de arquivo JPEG usando a extensão GD do PHP.
- `cropImageRegion(string $imgPath, int $x, int $y, int $w, int $h, int $canvasW, int $canvasH): string`: Recorta uma área da imagem proporcionalmente e retorna o caminho do recorte temporário.

### 3. Views e Assets Locais
- `app/Views/documents/review_workspace.php`
- `public/assets/css/review-workspace.css`
- `public/assets/js/review-workspace.js`
- Rotas registradas em `app/Config/Routes.php`.
