<?php
namespace App\Services;

use Config\Database;

class UnresolvedRelationshipService
{
    public function transferSource(int $sourceId, int $targetId): void
    {
        $db = Database::connect();
        $db->query('INSERT INTO unresolved_relationships
            (source_document_id, suggestion, reason, fingerprint, created_at)
            SELECT ?, suggestion, reason, fingerprint, created_at
            FROM unresolved_relationships WHERE source_document_id = ?
            ON CONFLICT (source_document_id, fingerprint) DO NOTHING', [$targetId, $sourceId]);
        $db->table('unresolved_relationships')->where('source_document_id', $sourceId)->delete();
    }

    public static function reason(array $relation, array $identities): ?string
    {
        $identity = new EntityIdentityService();
        $source = $identity->normalize((string) ($relation['source_name'] ?? ''));
        $target = $identity->normalize((string) ($relation['target_name'] ?? ''));
        if (!isset($identities[$source]) || !isset($identities[$target])) {
            return 'Uma ou ambas as pontas não correspondem às entidades desta extração. Revise os nomes e a fonte.';
        }
        if ($identities[$source] === $identities[$target]) {
            return 'As duas pontas correspondem à mesma identidade. Revise a classificação da relação.';
        }
        return null;
    }

    public function record(int $documentId, array $suggestion, string $reason): void
    {
        $result = Database::connect()->query('INSERT INTO unresolved_relationships
            (source_document_id, suggestion, reason, fingerprint) VALUES (?, ?::jsonb, ?, ?)
            ON CONFLICT (source_document_id, fingerprint) DO NOTHING', [
                $documentId, json_encode($suggestion, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $reason, EntityObservationService::fingerprint('unresolved', $suggestion, ['reason' => $reason]),
            ]);
        if (!$result) throw new \RuntimeException('Não foi possível preservar uma relação pendente.');
    }
}
