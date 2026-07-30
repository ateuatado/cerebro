<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Services\DocumentParserService;
use App\Services\DeepSeekService;

/**
 * DocumentReviewController — Workspace Interativo de Transcrição Histórica (Spec 7 & Spec 8)
 * Permite rotação de imagem de página, recorte de região para HTR e extração de entidades em 1-clique.
 */
class DocumentReviewController extends BaseController
{
    private EntityModel $entityModel;
    private DocumentParserService $parserService;
    private DeepSeekService $deepSeekService;

    public function __construct()
    {
        $this->entityModel     = new EntityModel();
        $this->parserService   = new DocumentParserService();
        $this->deepSeekService = new DeepSeekService();
    }

    private function getAbsoluteFilePath(array $attributes): string
    {
        $rel = $attributes['caminho_arquivo'] ?? '';
        if (empty($rel)) return '';

        if (file_exists($rel)) {
            return $rel;
        }

        $fullPath = WRITEPATH . 'uploads/' . ltrim($rel, '/\\');
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        $docPath = WRITEPATH . 'uploads/documents/' . ltrim(basename($rel), '/\\');
        if (file_exists($docPath)) {
            return $docPath;
        }

        return '';
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
        $cacheDir    = WRITEPATH . 'uploads/page_cache_' . $id;

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

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])) {
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'webp'        => 'image/webp',
                default       => 'image/jpeg',
            };
            return $this->response->setHeader('Content-Type', $mime)->setBody(file_get_contents($filePath));
        }

        $cacheDir  = WRITEPATH . 'uploads/page_cache_' . $id;
        $this->parserService->renderPdfPagesToCache($filePath, $cacheDir);

        $pageFile = $cacheDir . '/page_' . $page . '.jpg';
        if (!file_exists($pageFile)) {
            $pages = glob($cacheDir . '/page_*.jpg');
            $pageFile = $pages[0] ?? null;
        }

        if ($pageFile && file_exists($pageFile)) {
            return $this->response->setHeader('Content-Type', 'image/jpeg')->setBody(file_get_contents($pageFile));
        }

        return $this->response->setStatusCode(404)->setBody('Página não encontrada.');
    }

    /**
     * Rotaciona a página $page física no servidor e atualiza o OCR (/api/documentos/{id}/pagina/{page}/girar)
     */
    public function rotatePage(int $id, int $page)
    {
        $degrees = (int) ($this->request->getPost('degrees') ?? 90);
        $doc     = $this->entityModel->find($id);

        if (!$doc) {
            return $this->response->setJSON(['success' => false, 'error' => 'Documento não encontrado.']);
        }

        $attributes = is_string($doc['attributes']) ? json_decode($doc['attributes'], true) : ($doc['attributes'] ?? []);
        $filePath   = $this->getAbsoluteFilePath($attributes);
        $ext        = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $targetImg = null;
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])) {
            $targetImg = $filePath;
        } else {
            $cacheDir  = WRITEPATH . 'uploads/page_cache_' . $id;
            $targetImg = $cacheDir . '/page_' . $page . '.jpg';
        }

        if (!$targetImg || !file_exists($targetImg)) {
            return $this->response->setJSON(['success' => false, 'error' => 'Imagem da página não encontrada para rotação.']);
        }

        $ok = $this->parserService->rotateImageFile($targetImg, $degrees);

        if (!$ok) {
            return $this->response->setJSON(['success' => false, 'error' => 'Falha ao rotacionar imagem da página.']);
        }

        $newOcrText = $this->parserService->performOcr($targetImg, 'jpg');

        return $this->response->setJSON([
            'success' => true,
            'message' => "Página {$page} rotacionada {$degrees}° com sucesso!",
            'ocrText' => $newOcrText,
            'timestamp' => time(),
        ]);
    }

    /**
     * Extrai o texto manuscrito da região selecionada (/api/documentos/{id}/pagina/{page}/extrair-regiao)
     */
    public function extractRegion(int $id, int $page)
    {
        $x       = (int) $this->request->getPost('x');
        $y       = (int) $this->request->getPost('y');
        $w       = (int) $this->request->getPost('width');
        $h       = (int) $this->request->getPost('height');
        $canvasW = (int) $this->request->getPost('canvas_w');
        $canvasH = (int) $this->request->getPost('canvas_h');

        $doc = $this->entityModel->find($id);
        if (!$doc) {
            return $this->response->setJSON(['success' => false, 'error' => 'Documento não encontrado.']);
        }

        $attributes = is_string($doc['attributes']) ? json_decode($doc['attributes'], true) : ($doc['attributes'] ?? []);
        $filePath   = $this->getAbsoluteFilePath($attributes);
        $ext        = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $targetImg = null;
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])) {
            $targetImg = $filePath;
        } else {
            $cacheDir  = WRITEPATH . 'uploads/page_cache_' . $id;
            $targetImg = $cacheDir . '/page_' . $page . '.jpg';
        }

        if (!$targetImg || !file_exists($targetImg)) {
            return $this->response->setJSON(['success' => false, 'error' => 'Imagem da página não encontrada para recorte.']);
        }

        $cropFile = $this->parserService->cropImageRegion($targetImg, $x, $y, $w, $h, $canvasW, $canvasH);
        if (!$cropFile || !file_exists($cropFile)) {
            return $this->response->setJSON(['success' => false, 'error' => 'Não foi possível recortar a área selecionada.']);
        }

        $croppedText = $this->parserService->performOcr($cropFile, 'jpg');
        @unlink($cropFile);

        if (empty(trim($croppedText))) {
            $croppedText = "[Trecho selecionado sem texto reconhecido de forma legível pela IA]";
        }

        return $this->response->setJSON([
            'success' => true,
            'text'    => $croppedText,
        ]);
    }

    /**
     * Spec 8: Extrai Entidades & Grafo a partir da Seleção de Região em 1-Clique (/api/documentos/{id}/pagina/{page}/extrair-entidades-regiao)
     */
    public function extractEntitiesFromRegion(int $id, int $page)
    {
        $x       = (int) $this->request->getPost('x');
        $y       = (int) $this->request->getPost('y');
        $w       = (int) $this->request->getPost('width');
        $h       = (int) $this->request->getPost('height');
        $canvasW = (int) $this->request->getPost('canvas_w');
        $canvasH = (int) $this->request->getPost('canvas_h');

        $doc = $this->entityModel->find($id);
        if (!$doc) {
            return $this->response->setJSON(['success' => false, 'error' => 'Documento não encontrado.']);
        }

        $attributes = is_string($doc['attributes']) ? json_decode($doc['attributes'], true) : ($doc['attributes'] ?? []);
        $filePath   = $this->getAbsoluteFilePath($attributes);
        $ext        = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $targetImg = null;
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])) {
            $targetImg = $filePath;
        } else {
            $cacheDir  = WRITEPATH . 'uploads/page_cache_' . $id;
            $targetImg = $cacheDir . '/page_' . $page . '.jpg';
        }

        if (!$targetImg || !file_exists($targetImg)) {
            return $this->response->setJSON(['success' => false, 'error' => 'Imagem da página não encontrada.']);
        }

        $cropFile = $this->parserService->cropImageRegion($targetImg, $x, $y, $w, $h, $canvasW, $canvasH);
        if (!$cropFile || !file_exists($cropFile)) {
            return $this->response->setJSON(['success' => false, 'error' => 'Falha ao recortar a área selecionada.']);
        }

        $rawText = $this->parserService->performOcr($cropFile, 'jpg');
        @unlink($cropFile);

        $aiResult = $this->deepSeekService->extractFromCropText($doc['name'], $rawText);

        return $this->response->setJSON([
            'success'       => true,
            'transcription' => $aiResult['transcription'] ?? $rawText,
            'entities'      => $aiResult['entities'] ?? [],
            'relationships' => $aiResult['relationships'] ?? [],
        ]);
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
