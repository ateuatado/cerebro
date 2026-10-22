<?php

namespace App\Services;

use App\Models\EntityModel;
use Config\Database;
use RuntimeException;

/** Sugestões documentais não alteram atributos ou validação da identidade. */
class EntityObservationService
{
    /** Acompanha a mesclagem humana existente sem perder evidências por CASCADE. */
    public function transfer(int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) return;
        $db = Database::connect();
        $db->transBegin();
        try {
            $db->query('INSERT INTO entity_observations
                (entity_id, source_document_id, suggested_type, attributes, source_reference, fingerprint, created_at)
                SELECT CASE WHEN entity_id = ? THEN ? ELSE entity_id END,
                       CASE WHEN source_document_id = ? THEN ? ELSE source_document_id END,
                       suggested_type, attributes, source_reference, fingerprint, created_at
                FROM entity_observations WHERE entity_id = ? OR source_document_id = ?
                ON CONFLICT (entity_id, source_document_id, fingerprint) DO NOTHING',
                [$sourceId, $targetId, $sourceId, $targetId, $sourceId, $sourceId]);
            $db->table('entity_observations')->where('entity_id', $sourceId)
                ->orWhere('source_document_id', $sourceId)->delete();
            (new UnresolvedRelationshipService())->transferSource($sourceId, $targetId);
            if (!$db->transStatus()) throw new RuntimeException('Não foi possível transferir as evidências.');
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function record(array $entity, int $documentId, string $documentTitle, ?int $userId, array $reference = []): array
    {
        $identity = new EntityIdentityService();
        $name = trim((string) ($entity['name'] ?? ''));
        $type = strtolower(trim((string) ($entity['type'] ?? '')));
        if ($name === '' || !in_array($type, ['person', 'location', 'event', 'document'], true)) {
            throw new RuntimeException('Entidade sem nome ou com tipo inválido. Revise a extração.');
        }
        $attributes = is_array($entity['attributes'] ?? null) ? $entity['attributes'] : [];
        $reference = array_merge($reference, ['fonte' => $documentTitle]);
        $db = Database::connect();
        $db->transBegin();
        try {
            // Serializa somente a resolução da mesma identidade, inclusive entre uploads.
            $db->query('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                ($type === 'document' ? 'document|' : 'entity|') . $identity->normalize($name),
            ]);
            $existing = $identity->findForExtraction($type, $name);
            $created = false;
            if ($existing) {
                $id = (int) $existing['id'];
            } else {
                $id = (new EntityModel())->insert([
                    'name' => $name, 'type' => $type, 'status' => 'hypothesis',
                    'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_by' => $userId, 'validated_by' => null,
                ]);
                if (!$id) {
                    throw new RuntimeException('Não foi possível registrar a identidade.');
                }
                $id = (int) $id;
                $created = true;
            }
            $db->query('INSERT INTO entity_observations
                (entity_id, source_document_id, suggested_type, attributes, source_reference, fingerprint)
                VALUES (?, ?, ?, ?::jsonb, ?::jsonb, ?)
                ON CONFLICT (entity_id, source_document_id, fingerprint) DO NOTHING', [
                $id, $documentId, $type,
                json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                json_encode($reference, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                self::fingerprint($type, $attributes, $reference),
            ]);
            if (!$db->transStatus()) {
                throw new RuntimeException('Não foi possível registrar a fonte da entidade.');
            }
            $db->transCommit();
            return ['id' => $id, 'created' => $created];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public static function fingerprint(string $type, array $attributes, array $reference): string
    {
        $sort = static function (array $value) use (&$sort): array {
            if (!array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }
            return $value;
        };
        return hash('sha256', json_encode($sort([$type, $attributes, $reference]), JSON_THROW_ON_ERROR));
    }
}
