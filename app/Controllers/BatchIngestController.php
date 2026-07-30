<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\DocumentModel;
use App\Models\PersonModel;
use App\Models\LocationModel;
use App\Models\EventModel;
use App\Models\RelationshipModel;
use App\Services\DeepSeekService;
use App\Services\DocumentParserService;
use App\Services\AuthService;

/**
 * BatchIngestController — Upload em Lote via Web UI (TXT, PDF, JPG, PNG) e extração por IA
 */
class BatchIngestController extends BaseController
{
    private EntityModel       $entityModel;
    private RelationshipModel $relModel;
    private AuthService       $auth;

    public function __construct()
    {
        $this->entityModel = new EntityModel();
        $this->relModel    = new RelationshipModel();
        $this->auth        = new AuthService();
    }

    /**
     * GET /documentos/lote — Página principal de upload em lote
     */
    public function index(): string
    {
        return view('documents/batch');
    }

    /**
     * POST /api/documentos/upload-item — Recebe arquivo (.txt, .pdf, .jpg, .png, etc.), salva o arquivo, extrai texto e roda a IA
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

            // 2. Extrair conteúdo legível via DocumentParserService (OCR para imagens e PDFs)
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

            $userId = $this->auth->currentUser()['user_id'] ?? 1;
            $docModel = new DocumentModel();

            // 3. Verificar se o documento já foi cadastrado
            $existingDoc = $this->entityModel->where('name', $fileName)->where('type', 'document')->first();
            if ($existingDoc) {
                $documentId = $existingDoc['id'];
                $currentAttrs = is_string($existingDoc['attributes']) ? (json_decode($existingDoc['attributes'], true) ?? []) : ($existingDoc['attributes'] ?? []);
                $currentAttrs['caminho_arquivo'] = $savedFilePath;
                $currentAttrs['formato']         = strtoupper($ext);
                $currentAttrs['descricao']       = mb_substr($content, 0, 4000);
                $this->entityModel->update($documentId, [
                    'attributes' => json_encode($currentAttrs, JSON_UNESCAPED_UNICODE)
                ]);
            } else {
                $documentId = $docModel->insert([
                    'name'         => $fileName,
                    'type'         => 'document',
                    'status'       => 'confirmed',
                    'created_by'   => $userId,
                    'validated_by' => $userId,
                    'attributes'   => json_encode([
                        'titulo'                 => $fileName,
                        'formato'                => strtoupper($ext),
                        'caminho_arquivo'        => $savedFilePath,
                        'descricao'              => mb_substr($content, 0, 4000),
                        'instituicao_custodiadora'=> 'Upload em Lote (Web)',
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            // 4. Acionar IA DeepSeek
            $deepSeek = new DeepSeekService();
            $result   = $deepSeek->extractKnowledge($fileName, mb_substr($content, 0, 8000));

            $entities = $result['entities'] ?? [];
            $rels     = $result['relationships'] ?? [];

            $modelMap = [
                'person'   => new PersonModel(),
                'location' => new LocationModel(),
                'event'    => new EventModel(),
            ];

            // A) Salvar Entidades como hipóteses
            $entityNameMap = [];
            foreach ($entities as $eData) {
                $eName = trim($eData['name'] ?? '');
                $eType = $eData['type'] ?? 'person';
                if (empty($eName)) continue;

                $existingE = $this->entityModel->where('name', $eName)->first();
                if ($existingE) {
                    $entityNameMap[$eName] = $existingE['id'];
                    continue;
                }

                $model = $modelMap[$eType] ?? $modelMap['person'];
                $eid = $model->insert([
                    'type'       => in_array($eType, ['person','location','event']) ? $eType : 'person',
                    'name'       => $eName,
                    'status'     => 'hypothesis',
                    'attributes' => json_encode($eData['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
                    'created_by' => $userId,
                ]);
                if ($eid) {
                    $entityNameMap[$eName] = $eid;
                }
            }

            // B) Salvar Relações (< 100% gravadas como hipótese)
            $hypothesesCount = 0;
            $relsCount       = 0;
            foreach ($rels as $rData) {
                $srcName = trim($rData['source_name'] ?? '');
                $tgtName = trim($rData['target_name'] ?? '');
                $relType = trim($rData['relationship_type'] ?? 'associado_a');

                $srcId = $entityNameMap[$srcName] ?? null;
                $tgtId = $entityNameMap[$tgtName] ?? null;

                if (!$srcId || !$tgtId || $srcId === $tgtId) continue;

                $confidence = floatval($rData['confidence'] ?? 0.75);

                $this->relModel->insert([
                    'source_entity_id'  => $srcId,
                    'target_entity_id'  => $tgtId,
                    'relationship_type' => $relType,
                    'direction'         => ($rData['direction'] ?? 'directed') === 'symmetric' ? 'symmetric' : 'directed',
                    'confidence'        => round(max(0.5, min(0.99, $confidence)), 2),
                    'source_document_id'=> $documentId,
                    'source_reference'  => json_encode(['trecho' => $rData['excerpt'] ?? ''], JSON_UNESCAPED_UNICODE),
                    'status'            => 'hypothesis',
                    'created_by'        => $userId,
                ]);

                $relsCount++;
                $hypothesesCount++;
            }

            return $this->response->setJSON([
                'success'           => true,
                'fileName'          => $fileName,
                'documentId'        => $documentId,
                'entitiesExtracted' => count($entities),
                'relsExtracted'     => $relsCount,
                'hypothesesCount'   => $hypothesesCount,
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success'  => false,
                'fileName' => $fileName,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
