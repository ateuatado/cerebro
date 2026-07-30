<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Services\DocumentParserService;
use App\Services\DeepSeekService;
use App\Services\GeminiVisionService;

/**
 * DocumentReviewController — Workspace Interativo de Transcrição Histórica (Spec 7 & Spec 8)
 * Permite rotação de imagem de página, recorte de região para HTR e extração de entidades em 1-clique.
 */
class DocumentReviewController extends BaseController
{
    private EntityModel $entityModel;
    private DocumentParserService $parserService;
    private DeepSeekService $deepSeekService;
    private GeminiVisionService $geminiService;

    public function __construct()
    {
        $this->entityModel     = new EntityModel();
        $this->parserService   = new DocumentParserService();
        $this->deepSeekService = new DeepSeekService();
        $this->geminiService   = new GeminiVisionService();
    }

    private function getAbsoluteFilePath(array $attributes): string
    {
        $rel = str_replace('\\', '/', $attributes['caminho_arquivo'] ?? '');
        if (empty($rel)) return '';

        if (file_exists($rel)) {
            return str_replace('\\', '/', $rel);
        }

        $fullPath = str_replace('\\', '/', WRITEPATH . 'uploads/' . ltrim($rel, '/\\'));
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        $docPath = str_replace('\\', '/', WRITEPATH . 'uploads/documents/' . ltrim(basename($rel), '/\\'));
        if (file_exists($docPath)) {
            return $docPath;
        }

        return '';
    }

    private function getTargetPageImage(int $id, int $page, string $filePath): ?string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])) {
            return file_exists($filePath) ? $filePath : null;
        }

        $cacheDir = str_replace('\\', '/', WRITEPATH . 'uploads/page_cache_' . $id);
        $this->parserService->renderPdfPagesToCache($filePath, $cacheDir);

        $targetImg = $cacheDir . '/page_' . $page . '.jpg';
        if (!file_exists($targetImg)) {
            $pages = glob($cacheDir . '/page_*.jpg');
            if (!empty($pages)) {
                sort($pages, SORT_NATURAL);
                $targetImg = $pages[$page - 1] ?? ($pages[0] ?? null);
            }
        }

        return ($targetImg && file_exists($targetImg)) ? $targetImg : null;
    }

    private function saveBase64CropImage(?string $base64Data): ?string
    {
        if (empty($base64Data)) {
            log_message('debug', 'saveBase64CropImage: base64Data vazio');
            return null;
        }

        // Log do início dos dados recebidos para depuração
        $prefix = substr($base64Data, 0, 100);
        log_message('debug', 'saveBase64CropImage: prefix=' . $prefix . ', length=' . strlen($base64Data));

        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $imageType = $matches[1];
            $data = substr($base64Data, strpos($base64Data, ',') + 1);
            $decoded = base64_decode($data, true);
            if ($decoded !== false && strlen($decoded) > 50) {
                $cropFile = str_replace('\\', '/', WRITEPATH . 'uploads/crop_' . uniqid() . '.jpg');
                file_put_contents($cropFile, $decoded);
                log_message('debug', 'saveBase64CropImage: salvo em ' . $cropFile . ' tamanho=' . strlen($decoded));
                return $cropFile;
            }
            log_message('debug', 'saveBase64CropImage: decoded falhou ou muito pequeno (len=' . strlen($decoded ?? '') . ')');
        } else {
            log_message('debug', 'saveBase64CropImage: regex nao correspondeu');
        }

        return null;
    }

    /**
     * Tela principal do workspace de transcrição e revisão (/documentos/{id}/revisar)
     */
    public function review(int $id)
    {
        $doc = $this->entityModel->find($id);

        if (!$doc || $doc['type'] !== 'document') {
            return redirect()->to(base_url('documentos'))->with('error', 'Documento não encontrado.');
        }

        $attributes  = is_string($doc['attributes']) ? json_decode($doc['attributes'], true) : ($doc['attributes'] ?? []);
        $filePath    = $this->getAbsoluteFilePath($attributes);
        $cacheDir    = str_replace('\\', '/', WRITEPATH . 'uploads/page_cache_' . $id);

        $totalPages = 1;
        if (!empty($filePath) && file_exists($filePath)) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $renderRes  = $this->parserService->renderPdfPagesToCache($filePath, $cacheDir);
                $totalPages = max(1, $renderRes['totalPages'] ?? 1);
            }
        }

        $transcriptionText = $attributes['conteudo_transcrito'] ?? $doc['description'] ?? '';

        return view('documents/review_workspace', [
            'doc'               => $doc,
            'attributes'        => $attributes,
            'totalPages'        => $totalPages,
            'transcriptionText' => $transcriptionText,
        ]);
    }

    /**
     * Retorna/Transmite a imagem JPEG da página solicitada (/api/documentos/{id}/pagina/{page}/imagem)
     */
    public function getPageImage(int $id, int $page)
    {
        $doc = $this->entityModel->find($id);
        if (!$doc) {
            return $this->response->setStatusCode(404)->setBody('Documento não encontrado.');
        }

        $attributes = is_string($doc['attributes']) ? json_decode($doc['attributes'], true) : ($doc['attributes'] ?? []);
        $filePath   = $this->getAbsoluteFilePath($attributes);

        if (empty($filePath) || !file_exists($filePath)) {
            return $this->response->setStatusCode(404)->setBody('Arquivo não encontrado no servidor.');
        }

        $targetImg = $this->getTargetPageImage($id, $page, $filePath);

        if ($targetImg && file_exists($targetImg)) {
            return $this->response
                ->setHeader('Content-Type', 'image/jpeg')
                ->setHeader('Cache-Control', 'no-cache, must-revalidate')
                ->setBody(file_get_contents($targetImg));
        }

        return $this->response->setStatusCode(404)->setBody('Página não encontrada.');
    }

    /**
     * Rotaciona a página $page física no servidor e atualiza o OCR (/api/documentos/{id}/pagina/{page}/girar)
     */
    public function rotatePage(int $id, int $page)
    {
        try {
            $degrees = (int) ($this->request->getPost('degrees') ?? 90);
            $doc     = $this->entityModel->find($id);

            if (!$doc) {
                return $this->response->setJSON(['success' => false, 'error' => 'Documento não encontrado.']);
            }

            $attributes = is_string($doc['attributes']) ? json_decode($doc['attributes'], true) : ($doc['attributes'] ?? []);
            $filePath   = $this->getAbsoluteFilePath($attributes);
            $targetImg  = $this->getTargetPageImage($id, $page, $filePath);

            if (!$targetImg || !file_exists($targetImg)) {
                return $this->response->setJSON(['success' => false, 'error' => 'Imagem da página não encontrada para rotação.']);
            }

        $ok = $this->parserService->rotateImageFile($targetImg, $degrees);

            if (!$ok) {
                return $this->response->setJSON(['success' => false, 'error' => 'Falha ao rotacionar imagem da página.']);
            }

        $newOcrText = $this->parserService->performOcr($targetImg, 'jpg');
            // Sanitizar UTF-8 para evitar "Malformed UTF-8 characters" no JSON
            $newOcrText = mb_convert_encoding($newOcrText, 'UTF-8', 'UTF-8');

            return $this->response->setJSON([
                'success'   => true,
                'message'   => "Página {$page} rotacionada {$degrees}° com sucesso!",
                'ocrText'   => $newOcrText,
                'timestamp' => time(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'rotatePage error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'error' => 'Erro interno no servidor: ' . $e->getMessage()]);
        }
    }

    /**
     * Extrai o texto manuscrito da região selecionada (/api/documentos/{id}/pagina/{page}/extrair-regiao)
     * Usa Gemini Vision para ler a imagem do recorte diretamente (muito superior a OCR tradicional para manuscritos)
     */
    public function extractRegion(int $id, int $page)
    {
        try {
            $doc = $this->entityModel->find($id);
            if (!$doc) {
                return $this->response->setJSON(['success' => false, 'error' => 'Documento não encontrado.']);
            }

            $base64Crop = $this->request->getPost('crop_image_base64');

            if (empty($base64Crop)) {
                return $this->response->setJSON(['success' => false, 'error' => 'Nenhum recorte de imagem recebido.']);
            }

            // Nível 1: Gemini Vision (leitura superior de manuscritos)
            if ($this->geminiService->isAvailable()) {
                try {
                    $transcription = $this->geminiService->transcribeImage(
                        $doc['name'] . ' - Página ' . $page,
                        $base64Crop
                    );

                    if (!empty(trim($transcription))) {
                        return $this->response->setJSON([
                            'success' => true,
                            'text'    => $transcription,
                        ]);
                    }
                } catch (\Throwable $geminiError) {
                    log_message('warning', 'Gemini indisponível, tentando OCR + DeepSeek: ' . $geminiError->getMessage());
                }
            }

            // Nível 2: Salvar recorte como arquivo -> OCR local -> DeepSeek processa o texto
            $cropFile = $this->saveBase64CropImage($base64Crop);
            if ($cropFile && file_exists($cropFile)) {
                $ocrText = $this->parserService->performOcr($cropFile, 'jpg');
                if (!empty(trim($ocrText))) {
                    try {
                        $result = $this->deepSeekService->extractFromCropText(
                            $doc['name'] . ' - Página ' . $page,
                            $ocrText
                        );
                        $transcription = $result['transcription'] ?? $ocrText;
                        if (!empty(trim($transcription))) {
                            @unlink($cropFile);
                            return $this->response->setJSON([
                                'success' => true,
                                'text'    => $transcription,
                            ]);
                        }
                    } catch (\Throwable $dsError) {
                        log_message('warning', 'DeepSeek texto falhou, usando OCR puro: ' . $dsError->getMessage());
                    }
                }

                // Nível 3: OCR puro (fallback final)
                if (!empty(trim($ocrText))) {
                    @unlink($cropFile);
                    return $this->response->setJSON([
                        'success' => true,
                        'text'    => $ocrText,
                    ]);
                }
                @unlink($cropFile);
            }

            // Nenhum método funcionou
            return $this->response->setJSON([
                'success' => true,
                'text'    => '[Trecho selecionado sem texto reconhecido — os serviços de IA e OCR não estão disponíveis no momento.]',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'extractRegion error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'error' => 'Erro interno no servidor: ' . $e->getMessage()]);
        }
    }

    /**
     * Spec 8: Extrai Entidades & Grafo a partir da Seleção de Região em 1-Clique (/api/documentos/{id}/pagina/{page}/extrair-entidades-regiao)
     * Usa Gemini Vision para leitura da imagem + DeepSeek para extração de entidades
     */
    public function extractEntitiesFromRegion(int $id, int $page)
    {
        try {
            $doc = $this->entityModel->find($id);
            if (!$doc) {
                return $this->response->setJSON(['success' => false, 'error' => 'Documento não encontrado.']);
            }

            $base64Crop = $this->request->getPost('crop_image_base64');

            if (empty($base64Crop)) {
                return $this->response->setJSON(['success' => false, 'error' => 'Nenhum recorte de imagem recebido.']);
            }

            // Tenta usar Gemini Vision para leitura + DeepSeek para entidades
            if ($this->geminiService->isAvailable()) {
                try {
                    $aiResult = $this->geminiService->extractFromImage(
                        $doc['name'] . ' - Página ' . $page,
                        $base64Crop
                    );

                    if (!empty($aiResult['transcription'])) {
                        return $this->response->setJSON([
                            'success'       => true,
                            'transcription' => $aiResult['transcription'],
                            'entities'      => $aiResult['entities'] ?? [],
                            'relationships' => $aiResult['relationships'] ?? [],
                        ]);
                    }
                } catch (\Throwable $geminiError) {
                    log_message('warning', 'Gemini indisponível, usando DeepSeek fallback: ' . $geminiError->getMessage());
                }
            }

            // Fallback: OCR local -> DeepSeek extrai entidades do texto
            $cropFile = $this->saveBase64CropImage($base64Crop);
            if ($cropFile && file_exists($cropFile)) {
                $ocrText = $this->parserService->performOcr($cropFile, 'jpg');
                @unlink($cropFile);

                if (!empty(trim($ocrText))) {
                    try {
                        $aiResult = $this->deepSeekService->extractFromCropText(
                            $doc['name'] . ' - Página ' . $page,
                            $ocrText
                        );
                        return $this->response->setJSON([
                            'success'       => true,
                            'transcription' => $aiResult['transcription'] ?? $ocrText,
                            'entities'      => $aiResult['entities'] ?? [],
                            'relationships' => $aiResult['relationships'] ?? [],
                        ]);
                    } catch (\Throwable $dsError) {
                        log_message('warning', 'DeepSeek entidades falhou: ' . $dsError->getMessage());
                    }
                }
            }

            return $this->response->setJSON([
                'success' => true,
                'transcription' => '[Não foi possível extrair texto deste recorte — os serviços de IA e OCR não estão disponíveis no momento.]',
                'entities'      => [],
                'relationships' => [],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'extractEntitiesFromRegion error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'error' => 'Erro interno no servidor: ' . $e->getMessage()]);
        }
    }

    /**
     * Spec 8: Confirma e salva as entidades/relações aprovadas no Grafo de Hipóteses (/api/documentos/{id}/confirmar-entidades-regiao)
     */
    public function confirmRegionEntities(int $id)
    {
        $doc = $this->entityModel->find($id);
        if (!$doc) {
            return $this->response->setJSON(['success' => false, 'error' => 'Documento não encontrado.']);
        }

        $jsonInput = $this->request->getJSON(true);
        $entities  = $jsonInput['entities'] ?? [];
        $rels      = $jsonInput['relationships'] ?? [];
        $transcript= $jsonInput['transcription'] ?? '';

        $db = \Config\Database::connect();
        $createdEntitiesCount = 0;
        $createdRelsCount     = 0;
        $entityMap            = [];

        foreach ($entities as $e) {
            $name = trim($e['name'] ?? '');
            $type = $e['type'] ?? 'person';
            if (empty($name)) continue;

            $existing = $db->table('entities')
                           ->where('name', $name)
                           ->where('type', $type)
                           ->get()
                           ->getRowArray();

            if ($existing) {
                $entityMap[$name] = $existing['id'];
            } else {
                $newId = $this->entityModel->insert([
                    'name'        => $name,
                    'type'        => $type,
                    'status'      => 'hypothesis',
                    'description' => "Extraído do documento manuscrito: {$doc['name']}",
                    'attributes'  => json_encode($e['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                if ($newId) {
                    $entityMap[$name] = $newId;
                    $createdEntitiesCount++;
                }
            }
        }

        foreach ($rels as $r) {
            $srcName = trim($r['source_name'] ?? '');
            $tgtName = trim($r['target_name'] ?? '');

            $srcId = $entityMap[$srcName] ?? null;
            $tgtId = $entityMap[$tgtName] ?? null;

            if (!$srcId || !$tgtId || $srcId === $tgtId) continue;

            $relType = $r['relationship_type'] ?? 'mencionado_em';
            $conf    = (float) ($r['confidence'] ?? 0.85);

            $db->table('relationships')->insert([
                'source_entity_id'  => $srcId,
                'target_entity_id'  => $tgtId,
                'relationship_type' => $relType,
                'direction'         => $r['direction'] ?? 'directed',
                'confidence'        => $conf,
                'status'            => 'hypothesis',
                'source_document_id'=> $id,
                'source_reference'  => json_encode([
                    'documento_id' => $id,
                    'documento'    => $doc['name'],
                    'trecho'       => $r['excerpt'] ?? '',
                ], JSON_UNESCAPED_UNICODE),
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
            $createdRelsCount++;
        }

        if (!empty(trim($transcript))) {
            $attributes = is_string($doc['attributes']) ? json_decode($doc['attributes'], true) : ($doc['attributes'] ?? []);
            $attributes['conteudo_transcrito'] = ($attributes['conteudo_transcrito'] ?? '') . "\n\n[RECURSO HTR REGIONAL]:\n" . $transcript;

            $db->table('entities')
               ->where('id', $id)
               ->update(['attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE)]);
        }

        return $this->response->setJSON([
            'success'       => true,
            'message'       => "Salvas {$createdEntitiesCount} entidades e {$createdRelsCount} relações no Grafo com sucesso!",
            'entitiesSaved' => $createdEntitiesCount,
            'relsSaved'     => $createdRelsCount,
        ]);
    }

    /**
     * Salva as alterações da transcrição paginada no repositório PostgreSQL (/api/documentos/{id}/pagina/{page}/salvar-texto)
     */
    public function savePageText(int $id, int $page)
    {
        $text = (string) $this->request->getPost('conteudo_transcrito');
        $doc  = $this->entityModel->find($id);

        if (!$doc) {
            return $this->response->setJSON(['success' => false, 'error' => 'Documento não encontrado.']);
        }

        $attributes = is_string($doc['attributes']) ? json_decode($doc['attributes'], true) : ($doc['attributes'] ?? []);
        $attributes['conteudo_transcrito'] = $text;

        $db = \Config\Database::connect();
        $db->table('entities')
           ->where('id', $id)
           ->update(['attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE)]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Transcrição salva com sucesso no repositório!',
        ]);
    }
}
