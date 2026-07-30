<?php

namespace App\Services;

use App\Models\EntityModel;
use App\Models\RelationshipModel;
use App\Services\DeepSeekService;

/**
 * DocumentExtractionService — Pipeline nativo de extração em 2 estágios:
 * Lê a transcrição salva no repositório de texto bruto, sanitiza caracteres de controle,
 * divide em blocos (chunking) e grava as hipóteses de entidades e relacionamentos no PostgreSQL.
 */
class DocumentExtractionService
{
    private EntityModel       $entityModel;
    private RelationshipModel $relationshipModel;
    private DeepSeekService   $deepSeekService;

    public function __construct()
    {
        $this->entityModel       = new EntityModel();
        $this->relationshipModel = new RelationshipModel();
        $this->deepSeekService   = new DeepSeekService();
    }

    /**
     * Sanitiza o texto removendo caracteres de controle perigosos que quebram a decodificação JSON do PHP/API.
     */
    public function sanitizeTextForJson(string $text): string
    {
        // 1. Converter/Garantir UTF-8 válido
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        // 2. Remover caracteres de controle ASCII [\x00-\x08\x0B\x0C\x0E-\x1F\x7F] (preserva \t e \n)
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        return trim($clean ?: '');
    }

    /**
     * Divide o texto em blocos sequenciais por parágrafos com sobreposição.
     */
    public function chunkText(string $text, int $chunkSize = 2800): array
    {
        $sanitized = $this->sanitizeTextForJson($text);
        if (strlen($sanitized) <= $chunkSize) {
            return [$sanitized];
        }

        $lines = explode("\n", $sanitized);
        $chunks = [];
        $currentChunk = '';

        foreach ($lines as $line) {
            if (strlen($currentChunk) + strlen($line) > $chunkSize && !empty(trim($currentChunk))) {
                $chunks[] = trim($currentChunk);
                $currentChunk = '';
            }
            $currentChunk .= $line . "\n";
        }

        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return !empty($chunks) ? $chunks : [$sanitized];
    }

    /**
     * Executa a extração em 2 estágios a partir de um Documento salvo no banco.
     */
    public function extractFromDocument(int $documentId, ?int $userId = null): array
    {
        $db = \Config\Database::connect();
        $doc = $this->entityModel->find($documentId);

        if (!$doc || $doc['type'] !== 'document') {
            throw new \InvalidArgumentException("Documento ID {$documentId} não encontrado.");
        }

        $attributes = is_string($doc['attributes'])
            ? (json_decode($doc['attributes'], true) ?? [])
            : ($doc['attributes'] ?? []);

        $fullText = $attributes['conteudo_transcrito']
            ?? $attributes['descricao']
            ?? '';

        if (empty(trim($fullText))) {
            $attributes['extraction_status'] = 'error';
            $attributes['extraction_error']  = 'Documento sem texto bruto transcrito no repositório.';
            $this->entityModel->update($documentId, [
                'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE)
            ]);

            return [
                'success'           => false,
                'message'           => 'Documento sem conteúdo transcrito.',
                'entitiesExtracted' => 0,
                'relsExtracted'     => 0,
            ];
        }

        // Marcar status como 'processing'
        $attributes['extraction_status'] = 'processing';
        unset($attributes['extraction_error']);
        $this->entityModel->update($documentId, [
            'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE)
        ]);

        try {
            // Executar extração dividida em blocos
            $docTitle   = $doc['name'] ?? 'Documento #' . $documentId;
            $extraction = $this->deepSeekService->extractKnowledgeChunked($docTitle, $fullText, $attributes, 2800);

            $extractedEntities = $extraction['entities'] ?? [];
            $extractedRels     = $extraction['relationships'] ?? [];

            // Se o usuário não foi passado, tenta buscar o criador ou coordenador
            if (!$userId) {
                $userId = $doc['created_by'] ?? 1;
            }

            // Persistir entidades e relacionamentos no banco PostgreSQL
            $stats = $this->persistGraphData($documentId, $docTitle, $extractedEntities, $extractedRels, $userId);

            // Atualizar status para 'completed'
            $attributes['extraction_status']    = 'completed';
            $attributes['extracted_at']         = date('Y-m-d H:i:s');
            $attributes['entities_extracted']   = $stats['newEntities'];
            $attributes['rels_extracted']       = $stats['newRels'];

            $this->entityModel->update($documentId, [
                'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE)
            ]);

            return [
                'success'           => true,
                'documentId'        => $documentId,
                'entitiesExtracted' => $stats['newEntities'],
                'relsExtracted'     => $stats['newRels'],
                'totalEntitiesMap'  => count($stats['nameToIdMap']),
            ];

        } catch (\Throwable $e) {
            $attributes['extraction_status'] = 'error';
            $attributes['extraction_error']  = $e->getMessage();
            $this->entityModel->update($documentId, [
                'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE)
            ]);

            throw $e;
        }
    }

    /**
     * Grava entidades e relacionamentos extraídos no PostgreSQL como hipóteses lastreadas.
     */
    public function persistGraphData(int $docId, string $docTitle, array $entities, array $relationships, ?int $userId): array
    {
        $db = \Config\Database::connect();
        $nameToIdMap = [];
        $newEntitiesCount = 0;

        // A) Mapear/Cadastrar Entidades
        foreach ($entities as $ent) {
            $name = trim($ent['name'] ?? '');
            $type = strtolower(trim($ent['type'] ?? 'person'));

            if (empty($name)) continue;
            if (!in_array($type, ['person', 'location', 'event', 'document'])) {
                $type = 'person';
            }

            $key = mb_strtolower($name);

            $existing = $db->table('entities')
                ->where('type', $type)
                ->where('LOWER(name)', $key)
                ->get()
                ->getRowArray();

            if ($existing) {
                $eId = (int)$existing['id'];
            } else {
                $eId = $this->entityModel->insert([
                    'name'         => $name,
                    'type'         => $type,
                    'attributes'   => json_encode($ent['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
                    'status'       => 'hypothesis',
                    'created_by'   => $userId,
                    'validated_by' => null,
                ]);
                if ($eId) {
                    $newEntitiesCount++;
                }
            }

            $nameToIdMap[$key] = $eId;
        }

        // B) Cadastrar Relações no Grafo
        $newRelsCount = 0;
        foreach ($relationships as $rel) {
            $sName = mb_strtolower(trim($rel['source_name'] ?? ''));
            $tName = mb_strtolower(trim($rel['target_name'] ?? ''));
            $rType = strtolower(trim($rel['relationship_type'] ?? 'associado_a'));
            $rDir  = strtolower(trim($rel['direction'] ?? 'directed'));
            $conf  = floatval($rel['confidence'] ?? 0.85);
            $excr  = mb_substr(trim($rel['excerpt'] ?? ''), 0, 250);

            if (!isset($nameToIdMap[$sName]) || !isset($nameToIdMap[$tName])) {
                continue;
            }

            $sourceId = $nameToIdMap[$sName];
            $targetId = $nameToIdMap[$tName];

            if ($sourceId === $targetId) continue;

            $sourceRefObj = [
                'fonte'  => $docTitle,
                'trecho' => $excr ?: 'Citação extraída do documento',
            ];

            $relId = $this->relationshipModel->insert([
                'source_entity_id'   => $sourceId,
                'target_entity_id'   => $targetId,
                'relationship_type'  => $rType,
                'direction'          => $rDir === 'symmetric' ? 'symmetric' : 'directed',
                'status'             => 'hypothesis',
                'confidence'         => round(max(0.5, min(0.99, $conf)), 2),
                'source_document_id' => $docId,
                'source_reference'   => json_encode($sourceRefObj, JSON_UNESCAPED_UNICODE),
                'created_by'         => $userId,
                'validated_by'       => null,
            ]);

            if ($relId) {
                $newRelsCount++;
            }
        }

        return [
            'newEntities'  => $newEntitiesCount,
            'newRels'      => $newRelsCount,
            'nameToIdMap'  => $nameToIdMap,
        ];
    }
}
