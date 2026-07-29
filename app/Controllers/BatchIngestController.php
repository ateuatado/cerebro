<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\DocumentModel;
use App\Models\PersonModel;
use App\Models\LocationModel;
use App\Models\EventModel;
use App\Models\RelationshipModel;
use App\Services\DeepSeekService;
use App\Services\AuthService;

/**
 * BatchIngestController — Upload em Lote via Web UI e extração assíncrona por item
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
     * POST /api/documentos/upload-item — Recebe um arquivo do lote via AJAX, cadastra o documento e roda a IA
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
        $content  = file_get_contents($file->getTempName());

        if (empty(trim($content))) {
            return $this->response->setJSON([
                'success' => false,
                'fileName' => $fileName,
                'message'  => 'Arquivo vazio. Ignorado.',
            ]);
        }

        $userId = $this->auth->currentUser()['user_id'] ?? 1;
        $docModel = new DocumentModel();

        // 1. Verificar se o documento já foi cadastrado
        $existingDoc = $this->entityModel->where('name', $fileName)->where('type', 'document')->first();
        if ($existingDoc) {
            $documentId = $existingDoc['id'];
        } else {
            $documentId = $docModel->insert([
                'name'         => $fileName,
                'type'         => 'document',
                'status'       => 'confirmed',
                'created_by'   => $userId,
                'validated_by' => $userId,
                'attributes'   => json_encode([
                    'titulo'                 => $fileName,
                    'descricao'              => mb_substr($content, 0, 4000),
                    'instituicao_custodiadora'=> 'Upload em Lote (Web)',
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }

        // 2. Acionar IA DeepSeek
        try {
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
