<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\PersonModel;
use App\Models\LocationModel;
use App\Models\EventModel;
use App\Models\RelationshipModel;
use App\Services\DeepSeekService;
use App\Services\AuthService;

/**
 * ExtractionController — Extração automática via DeepSeek e interface de revisão
 */
class ExtractionController extends BaseController
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
     * POST /documentos/(:num)/extrair
     * Chama a IA para ler o documento e persistir hipóteses de entidades e relações.
     */
    public function extract(int $documentId)
    {
        $doc = $this->entityModel->find($documentId);
        if (!$doc || $doc['type'] !== 'document') {
            session()->setFlashdata('error', 'Documento não encontrado ou tipo inválido.');
            return redirect()->to('entidades');
        }

        $attrs = is_string($doc['attributes'])
            ? (json_decode($doc['attributes'], true) ?? [])
            : ($doc['attributes'] ?? []);

        // Texto do documento (procura em descricao, notas, conteudo ou titulo)
        $docText = $attrs['descricao'] ?? $attrs['notas'] ?? $attrs['titulo'] ?? $doc['name'];

        if (empty($docText)) {
            session()->setFlashdata('error', 'O documento não contém texto ou descrição para extração.');
            return redirect()->to('entidades/' . $documentId);
        }

        try {
            $deepSeek = new DeepSeekService();
            $result   = $deepSeek->extractKnowledge($doc['name'], $docText, $attrs);

            $extractedEntities = $result['entities'] ?? [];
            $extractedRels     = $result['relationships'] ?? [];

            $createdUser = $this->auth->currentUser()['user_id'] ?? null;

            // 1. Persistir Entidades como hipóteses
            $entityNameMap = []; // Nome -> ID
            $modelMap = [
                'person'   => new PersonModel(),
                'location' => new LocationModel(),
                'event'    => new EventModel(),
            ];

            foreach ($extractedEntities as $eData) {
                $name = trim($eData['name'] ?? '');
                $type = $eData['type'] ?? 'person';
                if (empty($name)) continue;

                // Verificar se já existe entidade com este nome
                $existing = $this->entityModel->where('name', $name)->first();
                if ($existing) {
                    $entityNameMap[$name] = $existing['id'];
                    continue;
                }

                $model = $modelMap[$type] ?? $modelMap['person'];
                $id = $model->insert([
                    'type'       => in_array($type, ['person','location','event']) ? $type : 'person',
                    'name'       => $name,
                    'status'     => 'hypothesis',
                    'attributes' => json_encode($eData['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
                    'created_by' => $createdUser,
                ]);

                if ($id) {
                    $entityNameMap[$name] = $id;
                }
            }

            // 2. Persistir Relações como hipóteses com source_document_id
            $countRels = 0;
            foreach ($extractedRels as $rData) {
                $srcName = trim($rData['source_name'] ?? '');
                $tgtName = trim($rData['target_name'] ?? '');
                $relType = trim($rData['relationship_type'] ?? 'associado_a');

                $srcId = $entityNameMap[$srcName] ?? null;
                $tgtId = $entityNameMap[$tgtName] ?? null;

                if (!$srcId || !$tgtId || $srcId === $tgtId) continue;

                $confidence = floatval($rData['confidence'] ?? 0.75);
                $excerpt    = $rData['excerpt'] ?? '';

                $this->relModel->insert([
                    'source_entity_id'  => $srcId,
                    'target_entity_id'  => $tgtId,
                    'relationship_type' => $relType,
                    'direction'         => ($rData['direction'] ?? 'directed') === 'symmetric' ? 'symmetric' : 'directed',
                    'confidence'        => round(max(0.5, min(0.99, $confidence)), 2),
                    'source_document_id'=> $documentId,
                    'source_reference'  => json_encode(['trecho' => $excerpt], JSON_UNESCAPED_UNICODE),
                    'status'            => 'hypothesis',
                    'created_by'        => $createdUser,
                ]);
                $countRels++;
            }

            session()->setFlashdata('success', "IA concluiu a análise! Extraídas " . count($entityNameMap) . " entidades e {$countRels} relações como hipóteses.");
            return redirect()->to('documentos/' . $documentId . '/revisar');

        } catch (\Exception $e) {
            session()->setFlashdata('error', 'Falha na extração por IA: ' . $e->getMessage());
            return redirect()->to('entidades/' . $documentId);
        }
    }

    /**
     * GET /documentos/(:num)/revisar
     * Interface de revisão dividida (Documento vs Hipóteses extraídas)
     */
    public function review(int $documentId): string
    {
        $doc = $this->entityModel->find($documentId);
        if (!$doc || $doc['type'] !== 'document') {
            session()->setFlashdata('error', 'Documento não encontrado.');
            return redirect()->to('entidades');
        }

        // Relações vinculadas a este documento
        $relationships = $this->relModel->findByDocument($documentId);

        // Enriquecer com entidades
        $relatedEntities = [];
        foreach ($relationships as $r) {
            $src = $this->entityModel->find($r['source_entity_id']);
            $tgt = $this->entityModel->find($r['target_entity_id']);
            if ($src) $relatedEntities[$src['id']] = $src;
            if ($tgt) $relatedEntities[$tgt['id']] = $tgt;
        }

        return view('extraction/review', [
            'doc'             => $doc,
            'relationships'   => $relationships,
            'relatedEntities' => $relatedEntities,
        ]);
    }

    /**
     * POST /documentos/(:num)/aprovar-todas
     * Promove todas as hipóteses vinculadas a um documento para 'confirmed' (apenas coordenador).
     */
    public function approveAll(int $documentId)
    {
        if (!$this->auth->canConfirm()) {
            session()->setFlashdata('error', 'Apenas o coordenador pode confirmar hipóteses em lote.');
            return redirect()->to('documentos/' . $documentId . '/revisar');
        }

        $userId = $this->auth->currentUser()['user_id'];
        $relationships = $this->relModel->findByDocument($documentId);

        $confirmedCount = 0;
        foreach ($relationships as $r) {
            if ($r['status'] === 'hypothesis') {
                $this->relModel->update($r['id'], [
                    'status'       => 'confirmed',
                    'validated_by' => $userId,
                ]);
                $confirmedCount++;

                // Confirmar também as entidades vinculadas se forem hipóteses
                foreach ([$r['source_entity_id'], $r['target_entity_id']] as $eid) {
                    $e = $this->entityModel->find($eid);
                    if ($e && $e['status'] === 'hypothesis') {
                        $this->entityModel->update($eid, [
                            'status'       => 'confirmed',
                            'validated_by' => $userId,
                        ]);
                    }
                }
            }
        }

        session()->setFlashdata('success', "{$confirmedCount} hipótese(s) confirmada(s) como fato documentado com sucesso.");
        return redirect()->to('documentos/' . $documentId . '/revisar');
    }
}
