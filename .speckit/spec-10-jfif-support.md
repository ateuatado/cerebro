# Especificação 10 — Suporte a Importação, Ingestão e Manipulação de Imagens JFIF

## Contexto & Objetivos

Muitos acervos históricos digitalizados e fotos baixadas da web utilizam o formato **JFIF** (`.jfif`), que é uma variação binária do padrão JPEG.

A **Spec 10** expande o pipeline de documentos do Cerebro para aceitar nativamente arquivos `.jfif` em:
1. Upload simples e em lote (Web UI + Drag & Drop).
2. Transcrição, leitura por OCR e extração automatizada via IA (DeepSeek / Gemini Vision).
3. Manipulação de imagem no Workspace de Transcrição (rotação de página, recorte regional/crop e exibição).

---

## Princípios não negociáveis (Constituição)

1. **Rastreabilidade e Fidelidade à Fonte (Princípio I)**: Arquivos `.jfif` devem ser gravados mantendo seu formato e extensão originais no repositório de documentos, garantindo a integridade da fonte primária.
2. **Compatibilidade Transparente**: O motor de tratamento de imagem (GD/Tesseract/Gemini) deve tratar arquivos `.jfif` com o mesmo nível de performance e suporte dado a `.jpg` e `.jpeg`.

---

## Requisitos Funcionais

### RF-1: Aceite de Upload e Ingestão em Lote
- Permitir a seleção e envio de arquivos com extensão `.jfif` no Dropzone do Upload em Lote e no formulário de inclusão/vínculo de documento.
- Atualização do filtro do front-end (`batch-ingest.js`) e back-end (`BatchIngestController.php`).

### RF-2: Parser e Manipulação de Imagem (GD / OCR)
- `DocumentParserService`:
  - Incluir `.jfif` na lista de extensões de imagem para OCR.
  - Ajustar métodos de carregamento de imagem (`imagecreatefromjpeg`) para aceitar arquivos `.jfif` em rotação e corte regional (crop tool).

### RF-3: Workspace de Transcrição e Exibição de Imagem
- `DocumentReviewController` e views de curadoria:
  - Reconhecer `.jfif` como formato de imagem direto para renderização visual e extração de recortes regional via Vision AI.
