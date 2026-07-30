<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Services\DocumentExtractionService;
use App\Services\AuthService;

/**
 * PendingExtractionController — Gerenciamento visual e acionamento manual de extrações pendentes por IA
 */
class PendingExtractionController extends BaseController
{
    private EntityModel               $entityModel;
    private DocumentExtractionService $extractionService;
    private AuthService               $auth;

    public function __construct()
    {
        $this->entityModel       = new EntityModel();
        $this->extractionService = new DocumentExtractionService();
        $this->auth              = new AuthService();
    }

    /**
     * GET /documentos/pendentes — Lista todos os documentos no repositório pendentes de extração ou com erro
     */
    public function index(): string
    {
        $db = \Config\Database::connect();
        $docs = $db->table('entities')
            ->where('type', 'document')
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        $pendingDocs = [];
        $totalChars  = 0;

        foreach ($docs as $doc) {
            $attrs = is_string($doc['attributes'])
                ? (json_decode($doc['attributes'], true) ?? [])
                : ($doc['attributes'] ?? []);

            $status = $attrs['extraction_status'] ?? 'pending';
            $text   = $attrs['conteudo_transcrito'] ?? $attrs['descricao'] ?? '';

            // Filtrar documentos que ainda não foram concluídos ou que estão com erro/pendentes
            if (in_array($status, ['pending', 'error']) || empty($attrs['extracted_at'])) {
                $charCount = strlen($text);
                $totalChars += $charCount;

                $pendingDocs[] = [
                    'id'                 => (int)$doc['id'],
                    'name'               => $doc['name'],
                    'formato'            => $attrs['formato'] ?? 'PDF',
                    'caminho_arquivo'    => $attrs['caminho_arquivo'] ?? '',
                    'status'             => $status,
                    'erro'               => $attrs['extraction_error'] ?? null,
                    'tamanho_caracteres' => $charCount,
                    'conteudo_transcrito'=> $text,
                    'created_at'         => $doc['created_at'],
                ];
            }
        }

        return view('documents/pending', [
            'pendingDocs'  => $pendingDocs,
            'totalPending' => count($pendingDocs),
            'totalChars'   => $totalChars,
        ]);
    }

    /**
     * POST /api/documentos/pendentes/salvar-texto — Atualiza manualmente a transcrição bruta no repositório
     */
    public function updateText()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Requisição inválida']);
        }

        $docId   = (int)$this->request->getPost('document_id');
        $newText = trim($this->request->getPost('conteudo_transcrito') ?? '');

        if (!$docId || empty($newText)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'ID do documento ou texto em branco.']);
        }

        $doc = $this->entityModel->find($docId);
        if (!$doc) {
            return $this->response->setStatusCode(444)->setJSON(['error' => 'Documento não encontrado.']);
        }

        $attrs = is_string($doc['attributes'])
            ? (json_decode($doc['attributes'], true) ?? [])
            : ($doc['attributes'] ?? []);

        $attrs['conteudo_transcrito'] = $newText;
        $attrs['descricao']           = mb_substr($newText, 0, 1000) . '...';
        $attrs['extraction_status']   = 'pending';
        unset($attrs['extraction_error']);

        $this->entityModel->update($docId, [
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE)
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Transcrição atualizada com sucesso no repositório!',
            'docId'   => $docId,
            'chars'   => strlen($newText),
        ]);
    }

    /**
     * POST /api/documentos/pendentes/extrair/{id} — Executa a extração por IA de um único documento
     */
    public function extractSingle(int $docId)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Requisição inválida']);
        }

        try {
            $userId = $this->auth->currentUser()['user_id'] ?? 1;
            $res    = $this->extractionService->extractFromDocument($docId, $userId);

            return $this->response->setJSON([
                'success'           => true,
                'documentId'        => $docId,
                'entitiesExtracted' => $res['entitiesExtracted'] ?? 0,
                'relsExtracted'     => $res['relsExtracted'] ?? 0,
                'message'           => 'Extração concluída com sucesso!',
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/documentos/pendentes/processar-todos — Processa todos os documentos pendentes em lote
     */
    public function extractBatch()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Requisição inválida']);
        }

        $db = \Config\Database::connect();
        $docs = $db->table('entities')
            ->where('type', 'document')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $userId = $this->auth->currentUser()['user_id'] ?? 1;
        $totalProcessed = 0;
        $totalEntities  = 0;
        $totalRels      = 0;

        foreach ($docs as $doc) {
            $attrs = is_string($doc['attributes'])
                ? (json_decode($doc['attributes'], true) ?? [])
                : ($doc['attributes'] ?? []);

            $status = $attrs['extraction_status'] ?? 'pending';
            if (in_array($status, ['pending', 'error']) || empty($attrs['extracted_at'])) {
                try {
                    $res = $this->extractionService->extractFromDocument((int)$doc['id'], $userId);
                    $totalProcessed++;
                    $totalEntities += ($res['entitiesExtracted'] ?? 0);
                    $totalRels     += ($res['relsExtracted'] ?? 0);
                } catch (\Throwable $e) {
                    // Continua nos próximos documentos
                }
            }
        }

        return $this->response->setJSON([
            'success'        => true,
            'totalProcessed' => $totalProcessed,
            'totalEntities'  => $totalEntities,
            'totalRels'      => $totalRels,
            'message'        => "{$totalProcessed} documentos processados em lote!",
        ]);
    }
}
