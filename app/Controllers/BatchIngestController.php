<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\DocumentModel;
use App\Services\DocumentParserService;
use App\Services\DocumentExtractionService;
use App\Services\AuthService;

/**
 * BatchIngestController — Upload em Lote via Web UI (TXT, PDF, JPG, PNG) e extração em 2 estágios
 */
class BatchIngestController extends BaseController
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
     * GET /documentos/lote — Página principal de upload em lote
     */
    public function index(): string
    {
        return view('documents/batch');
    }

    /**
     * POST /api/documentos/upload-item — Ingestão em 2 Estágios:
     * Estágio 1: Salva o arquivo e grava o texto bruto completo no repositório com status 'pending'.
     * Estágio 2: Tenta executar a extração por IA tokenizada em blocos sem perder o documento se a IA oscilar.
     */
    public function uploadItem()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Requisição inválida']);
        }

        $file = $this->request->getFile('file');
        if (!$file || !$file->isValid()) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => $file ? $file->getErrorString() : 'Arquivo não enviado ou corrompido.'
            ]);
        }

        $fileName = $file->getClientName();
        $ext      = strtolower($file->getClientExtension());

        try {
            // 1. Salvar o arquivo permanentemente em writable/uploads/documents/
            $uploadDir = WRITEPATH . 'uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $savedFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileName);
            $file->move($uploadDir, $savedFileName);
            $savedFilePath = $uploadDir . $savedFileName;

            // 2. ESTÁGIO 1: Extrair conteúdo bruto via DocumentParserService
            $docParser   = new DocumentParserService();
            $parseResult = $docParser->parseFile($savedFilePath, $ext);
            $content     = $parseResult['text'] ?? '';

            if (empty(trim($content))) {
                return $this->response->setJSON([
                    'success'  => false,
                    'fileName' => $fileName,
                    'message'  => 'Arquivo sem conteúdo legível. Ignorado.',
                ]);
            }

            $userId   = $this->auth->currentUser()['user_id'] ?? 1;
            $docModel = new DocumentModel();

            // Salvar no repositório de texto bruto do PostgreSQL
            $attributes = [
                'titulo'                  => $fileName,
                'formato'                 => strtoupper($ext),
                'caminho_arquivo'         => $savedFilePath,
                'descricao'               => mb_substr($content, 0, 1000) . '...',
                'conteudo_transcrito'     => $content,
                'instituicao_custodiadora'=> 'Upload em Lote (Web)',
                'extraction_status'       => 'pending',
            ];

            $existingDoc = $this->entityModel->where('name', $fileName)->where('type', 'document')->first();
            if ($existingDoc) {
                $documentId = (int)$existingDoc['id'];
                $currentAttrs = is_string($existingDoc['attributes'])
                    ? (json_decode($existingDoc['attributes'], true) ?? [])
                    : ($existingDoc['attributes'] ?? []);
                
                $mergedAttrs = array_merge($currentAttrs, $attributes);
                $this->entityModel->update($documentId, [
                    'attributes' => json_encode($mergedAttrs, JSON_UNESCAPED_UNICODE)
                ]);
            } else {
                $documentId = $docModel->insert([
                    'name'         => $fileName,
                    'type'         => 'document',
                    'status'       => 'confirmed',
                    'created_by'   => $userId,
                    'validated_by' => $userId,
                    'attributes'   => json_encode($attributes, JSON_UNESCAPED_UNICODE),
                ]);
            }

            // 3. ESTÁGIO 2: Tentar disparar a extração tokenizada por IA em blocos
            try {
                $result = $this->extractionService->extractFromDocument($documentId, $userId);

                return $this->response->setJSON([
                    'success'           => true,
                    'fileName'          => $fileName,
                    'documentId'        => $documentId,
                    'entitiesExtracted' => $result['entitiesExtracted'] ?? 0,
                    'relsExtracted'     => $result['relsExtracted'] ?? 0,
                    'hypothesesCount'   => $result['relsExtracted'] ?? 0,
                    'status'            => 'completed',
                ]);
            } catch (\Throwable $aiException) {
                // Se a IA oscilar, o documento permanece salvo com status 'error' para reprocessamento na tela de pendentes
                return $this->response->setJSON([
                    'success'    => true,
                    'fileName'   => $fileName,
                    'documentId' => $documentId,
                    'status'     => 'pending',
                    'message'    => 'Documento salvo no repositório. Extração pendente: ' . $aiException->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success'  => false,
                'fileName' => $fileName,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
