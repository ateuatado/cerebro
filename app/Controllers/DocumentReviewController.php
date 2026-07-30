<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Services\DocumentParserService;
use App\Services\DeepSeekService;

/**
 * DocumentReviewController — Workspace Interativo de Transcrição Histórica (Spec 7)
 * Permite rotação de imagem de página, recorte de região com IA e salvamento paginado.
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
        $filePath    = WRITEPATH . 'uploads/' . ($attributes['caminho_arquivo'] ?? '');
        $cacheDir    = WRITEPATH . 'uploads/page_cache_' . $id;

        $totalPages = 1;
        if (file_exists($filePath)) {
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
        $filePath   = WRITEPATH . 'uploads/' . ($attributes['caminho_arquivo'] ?? '');

        if (!file_exists($filePath)) {
            return $this->response->setStatusCode(404)->setBody('Arquivo não encontrado.');
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Se for imagem direta (.jpg, .png, etc)
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])) {
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'webp'        => 'image/webp',
                default       => 'image/jpeg',
            };
            return $this->response->setHeader('Content-Type', $mime)->setBody(file_get_contents($filePath));
        }

        // Se for PDF, busca na pasta de cache da página
        $cacheDir  = WRITEPATH . 'uploads/page_cache_' . $id;
        $this->parserService->renderPdfPagesToCache($filePath, $cacheDir);

        $pageFile = $cacheDir . '/page_' . $page . '.jpg';
        if (!file_exists($pageFile)) {
            $pageFile = glob($cacheDir . '/page_*.jpg')[0] ?? null;
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
        $filePath   = WRITEPATH . 'uploads/' . ($attributes['caminho_arquivo'] ?? '');
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

        // Executar rotação via PHP GD
        $ok = $this->parserService->rotateImageFile($targetImg, $degrees);

        if (!$ok) {
            return $this->response->setJSON(['success' => false, 'error' => 'Falha ao rotacionar imagem da página.']);
        }

        // Refazer OCR na nova orientação da página
        $newOcrText = $this->parserService->performOcr($targetImg, 'jpg');

        return $this->response->setJSON([
            'success' => true,
            'message' => "Página {$page} rotacionada {$degrees}° com sucesso!",
            'ocrText' => $newOcrText,
            'timestamp' => time(),
        ]);
    }

    /**
     * Extrai o texto manuscrito/tabelado da região selecionada (Crop Tool com IA) (/api/documentos/{id}/pagina/{page}/extrair-regiao)
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
        $filePath   = WRITEPATH . 'uploads/' . ($attributes['caminho_arquivo'] ?? '');
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

        // 1. Recortar a região selecionada
        $cropFile = $this->parserService->cropImageRegion($targetImg, $x, $y, $w, $h, $canvasW, $canvasH);
        if (!$cropFile || !file_exists($cropFile)) {
            return $this->response->setJSON(['success' => false, 'error' => 'Não foi possível recortar a área selecionada.']);
        }

        // 2. Realizar OCR/HTR focado no recorte
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
