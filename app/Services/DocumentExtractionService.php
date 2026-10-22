<?php

namespace App\Services;

use App\Models\EntityModel;
use App\Models\RelationshipModel;
use App\Models\EntityAttributeVocabularyModel;
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
    private GeminiVisionService $geminiService;
    private EntityIdentityService $identityService;

    public function __construct()
    {
        $this->entityModel       = new EntityModel();
        $this->relationshipModel = new RelationshipModel();
        $this->deepSeekService   = new DeepSeekService();
        $this->geminiService     = new GeminiVisionService();
        $this->identityService   = new EntityIdentityService();
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
    public function chunkText(string $text, int $chunkSize = 10000): array
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
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

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
            $docTitle   = $doc['name'] ?? 'Documento #' . $documentId;
            $extraction = [];

            // Carregar vocabulário controlado para injeção nos prompts (Spec 11)
            $vocabModel = new EntityAttributeVocabularyModel();
            $vocabulary = $vocabModel->getAllGroupedByType();

            // 1. Tentar Gemini 2.0 Flash primeiro (1 chamada ultra-rápida de ~8s para até 50.000 caracteres)
            if ($this->geminiService->isAvailable()) {
                try {
                    $extraction = $this->geminiService->extractKnowledgeFromText($docTitle, $fullText, $vocabulary);
                } catch (\Throwable $geminiErr) {
                    log_message('warning', 'Gemini extraction error, falling back to DeepSeek: ' . $geminiErr->getMessage());
                }
            }

            // 2. Fallback: DeepSeek chunked em blocos grandes de 10.000 caracteres (máx 4-5 chamadas)
            if (empty($extraction['entities'])) {
                $extraction = $this->deepSeekService->extractKnowledgeChunked($docTitle, $fullText, $attributes, 10000, $vocabulary);
            }

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
                'unresolvedRels'    => $stats['unresolvedRels'],
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
        $db->transBegin();
        try {
            $db->query('SELECT pg_advisory_xact_lock(?, ?)', [16, $docId]);
            $nameToIdMap = [];
            $newEntitiesCount = 0;

            // Ordem estável de locks evita ciclos entre documentos concorrentes.
            usort($entities, fn(array $a, array $b): int =>
                strcmp($this->identityService->normalize((string) ($a['name'] ?? '')),
                    $this->identityService->normalize((string) ($b['name'] ?? ''))));

            // A) Mapear/Cadastrar Entidades
            foreach ($entities as $ent) {
                $name = trim($ent['name'] ?? '');
                $type = strtolower(trim($ent['type'] ?? 'person'));

                if (empty($name)) continue;
                if (!in_array($type, ['person', 'location', 'event', 'document'])) {
                    throw new \RuntimeException('Tipo inválido para ' . $name . '. Revise a extração.');
                }

                $key = $this->identityService->normalize($name);
                $record = (new EntityObservationService())->record(
                    $ent, $docId, $docTitle, $userId,
                    is_array($ent['source_reference'] ?? null) ? $ent['source_reference'] : []
                );
                $eId = $record['id'];
                if ($record['created']) {
                    $newEntitiesCount++;
                }

                if (isset($nameToIdMap[$key]) && $nameToIdMap[$key] !== $eId) {
                    throw new \RuntimeException('Documento e entidade com nome indistinguível: ' . $name . '. Revise a extração.');
                }
                $nameToIdMap[$key] = $eId;
            }

            // B) Cadastrar Relações no Grafo
            $newRelsCount = 0;
            $unresolvedCount = 0;
            foreach ($relationships as $rel) {
                $sName = $this->identityService->normalize((string) ($rel['source_name'] ?? ''));
                $tName = $this->identityService->normalize((string) ($rel['target_name'] ?? ''));
                $rType = strtolower(trim($rel['relationship_type'] ?? 'associado_a'));
                $rDir  = strtolower(trim($rel['direction'] ?? 'directed'));
                $conf  = floatval($rel['confidence'] ?? 0.85);
                $excr  = mb_substr(trim($rel['excerpt'] ?? ''), 0, 250);

                $reason = UnresolvedRelationshipService::reason($rel, $nameToIdMap);
                if ($reason !== null) {
                    (new UnresolvedRelationshipService())->record($docId, $rel, $reason);
                    $unresolvedCount++;
                    continue;
                }

                $sourceId = $nameToIdMap[$sName];
                $targetId = $nameToIdMap[$tName];

                $sourceRefObj = [
                    'fonte'  => $docTitle,
                    'trecho' => $excr ?: 'Citação extraída do documento',
                ];

                $direction = $rDir === 'symmetric' ? 'symmetric' : 'directed';
                $referenceJson = json_encode($sourceRefObj, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $duplicate = $db->query('SELECT id FROM relationships
                    WHERE source_entity_id = ? AND target_entity_id = ?
                    AND relationship_type = ? AND direction = ? AND source_document_id = ?
                    AND source_reference = ?::jsonb LIMIT 1',
                    [$sourceId, $targetId, $rType, $direction, $docId, $referenceJson]
                )->getRowArray();
                if ($duplicate) continue;

                $relId = $this->relationshipModel->insert([
                    'source_entity_id'   => $sourceId,
                    'target_entity_id'   => $targetId,
                    'relationship_type'  => $rType,
                    'direction'          => $direction,
                    'status'             => 'hypothesis',
                    'confidence'         => round(max(0.5, min(0.99, $conf)), 2),
                    'source_document_id' => $docId,
                    'source_reference'   => $referenceJson,
                    'created_by'         => $userId,
                    'validated_by'       => null,
                ]);

                if ($relId) {
                    $newRelsCount++;
                } else {
                    throw new \RuntimeException('Não foi possível salvar uma relação da extração.');
                }
            }

            if (!$db->transStatus()) {
                throw new \RuntimeException('A extração não pôde ser salva integralmente.');
            }
            $db->transCommit();
            return [
                'newEntities'  => $newEntitiesCount,
                'newRels'      => $newRelsCount,
                'unresolvedRels' => $unresolvedCount,
                'nameToIdMap'  => $nameToIdMap,
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
