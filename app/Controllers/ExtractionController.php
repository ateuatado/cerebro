<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\PersonModel;
use App\Models\LocationModel;
use App\Models\EventModel;
use App\Models\RelationshipModel;
use App\Services\DeepSeekService;
use App\Services\DocumentParserService;
use App\Services\AuthService;

/**
 * ExtractionController — Extração automática via DeepSeek + OCR e interface de revisão
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
     * POST /documentos/(:num)/vincular-arquivo
     * Recebe a foto/imagem enviada diretamente da tela de revisão, salva, roda OCR e extrai com IA.
     */
    public function attachFile(int $documentId)
    {
        $doc = $this->entityModel->find($documentId);
        if (!$doc || $doc['type'] !== 'document') {
            session()->setFlashdata('error', 'Documento não encontrado.');
            return redirect()->to('documentos');
        }

        $file = $this->request->getFile('file');
        if (!$file || !$file->isValid()) {
            session()->setFlashdata('error', 'Selecione um arquivo de imagem (JPG, PNG) ou PDF válido.');
            return redirect()->to('documentos/' . $documentId . '/revisar');
        }

        try {
            $uploadDir = WRITEPATH . 'uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower($file->getClientExtension());
            $savedFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file->getClientName());
            $file->move($uploadDir, $savedFileName);
            $savedFilePath = $uploadDir . $savedFileName;

            // 1. Roda OCR + Parser de Texto
            $docParser   = new DocumentParserService();
            $parseResult = $docParser->parseFile($savedFilePath, $ext);
            $content     = $parseResult['text'] ?? '';

            $attrs = is_string($doc['attributes'])
                ? (json_decode($doc['attributes'], true) ?? [])
                : ($doc['attributes'] ?? []);

            $attrs['caminho_arquivo'] = $savedFilePath;
            $attrs['formato']         = strtoupper($ext);
            $attrs['descricao']       = mb_substr($content, 0, 4000);

            $this->entityModel->update($documentId, [
                'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE)
            ]);

            // 2. Acionar extração automática via IA DeepSeek
            return $this->extract($documentId);

        } catch (\Exception $e) {
            session()->setFlashdata('error', 'Erro ao vincular arquivo: ' . $e->getMessage());
            return redirect()->to('documentos/' . $documentId . '/revisar');
        }
    }

    /**
     * POST /documentos/(:num)/extrair
     * Chama OCR (se imagem/PDF) e a IA para reler o documento e persistir hipóteses de entidades e relações.
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

        // Se o arquivo físico existe no servidor, rodar OCR/Parser para garantir texto atualizado
        $docText = $attrs['descricao'] ?? '';
        $filePath = $attrs['caminho_arquivo'] ?? '';
        $ext      = strtolower($attrs['formato'] ?? pathinfo($doc['name'], PATHINFO_EXTENSION));

        if (!empty($filePath) && file_exists($filePath)) {
            try {
                $docParser   = new DocumentParserService();
                $parseResult = $docParser->parseFile($filePath, $ext);
                if (!empty(trim($parseResult['text']))) {
                    $docText = $parseResult['text'];
                    $attrs['descricao'] = mb_substr($docText, 0, 4000);
                    $this->entityModel->update($documentId, [
                        'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE)
                    ]);
                }
            } catch (\Exception $e) {
                // Mantém docText existente se a relitura falhar
            }
        }

        if (empty($docText)) {
            $docText = $attrs['notas'] ?? $attrs['titulo'] ?? $doc['name'];
        }

        try {
            $deepSeek = new DeepSeekService();
            $result   = $deepSeek->extractKnowledge($doc['name'], mb_substr($docText, 0, 8000), $attrs);

            $extractedEntities = $result['entities'] ?? [];
            $extractedRels     = $result['relationships'] ?? [];

            $createdUser = $this->auth->currentUser()['user_id'] ?? 1;

            // 1. Persistir Entidades como hipóteses
            $entityNameMap = [];
            $modelMap = [
                'person'   => new PersonModel(),
                'location' => new LocationModel(),
                'event'    => new EventModel(),
            ];

            foreach ($extractedEntities as $eData) {
                $name = trim($eData['name'] ?? '');
                $type = $eData['type'] ?? 'person';
                if (empty($name)) continue;

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

            session()->setFlashdata('success', "Re-leitura concluída! Extraídas " . count($entityNameMap) . " entidades e {$countRels} relações como hipóteses.");
            return redirect()->to('documentos/' . $documentId . '/revisar');

        } catch (\Exception $e) {
            session()->setFlashdata('error', 'Falha na extração por IA: ' . $e->getMessage());
            return redirect()->to('entidades/' . $documentId);
        }
    }

    /**
     * POST /documentos/reprocessar-tudo
     * Relê e re-extrai conhecimento via OCR + IA de todos os documentos já cadastrados no banco.
     */
    public function reprocessAll()
    {
        $documents = $this->entityModel->where('type', 'document')->findAll();

        if (empty($documents)) {
            session()->setFlashdata('info', 'Nenhum documento encontrado no banco de dados para reprocessamento.');
            return redirect()->to('documentos');
        }

        $reprocessedCount = 0;
        $totalEntities    = 0;
        $totalRels        = 0;

        $docParser = new DocumentParserService();
        $deepSeek  = new DeepSeekService();
        $modelMap  = [
            'person'   => new PersonModel(),
            'location' => new LocationModel(),
            'event'    => new EventModel(),
        ];
        $userId = $this->auth->currentUser()['user_id'] ?? 1;

        foreach ($documents as $doc) {
            $attrs = is_string($doc['attributes'])
                ? (json_decode($doc['attributes'], true) ?? [])
                : ($doc['attributes'] ?? []);

            $filePath = $attrs['caminho_arquivo'] ?? '';
            $ext      = strtolower($attrs['formato'] ?? pathinfo($doc['name'], PATHINFO_EXTENSION));
            $docText  = $attrs['descricao'] ?? '';

            // Se o arquivo físico existe, executa OCR
            if (!empty($filePath) && file_exists($filePath)) {
                try {
                    $parseResult = $docParser->parseFile($filePath, $ext);
                    if (!empty(trim($parseResult['text']))) {
                        $docText = $parseResult['text'];
                        $attrs['descricao'] = mb_substr($docText, 0, 4000);
                        $this->entityModel->update($doc['id'], [
                            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE)
                        ]);
                    }
                } catch (\Exception $e) {}
            }

            if (empty($docText)) continue;

            try {
                $result = $deepSeek->extractKnowledge($doc['name'], mb_substr($docText, 0, 8000), $attrs);
                $entities = $result['entities'] ?? [];
                $rels     = $result['relationships'] ?? [];

                $entityNameMap = [];
                foreach ($entities as $eData) {
                    $eName = trim($eData['name'] ?? '');
                    $eType = $eData['type'] ?? 'person';
                    if (empty($eName)) continue;

                    $existing = $this->entityModel->where('name', $eName)->first();
                    if ($existing) {
                        $entityNameMap[$eName] = $existing['id'];
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
                    if ($eid) $entityNameMap[$eName] = $eid;
                }

                $relsCount = 0;
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
                        'source_document_id'=> $doc['id'],
                        'source_reference'  => json_encode(['trecho' => $rData['excerpt'] ?? ''], JSON_UNESCAPED_UNICODE),
                        'status'            => 'hypothesis',
                        'created_by'        => $userId,
                    ]);
                    $relsCount++;
                }

                $totalEntities += count($entities);
                $totalRels     += $relsCount;
                $reprocessedCount++;

            } catch (\Exception $e) {}

            usleep(200000);
        }

        session()->setFlashdata('success', "Reprocessamento concluído! {$reprocessedCount} documento(s) re-lidos, {$totalEntities} entidade(s) e {$totalRels} relação(ões) geradas.");
        return redirect()->to('documentos');
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

        $relationships = $this->relModel->findByDocument($documentId);

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
