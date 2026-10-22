<?php

namespace App\Models;

use CodeIgniter\Model;

class EntityObservationModel extends Model
{
    protected $table = 'entity_observations';
    protected $returnType = 'array';

    /** Uma linha por identidade/fonte, sem criar afirmação em relationships. */
    public function findGraphLinks(int $researchId = 0): array
    {
        $builder = $this->db->table('entity_observations o')
            ->select('o.entity_id, o.source_document_id, COUNT(*) AS occurrences')
            ->where('o.entity_id <> o.source_document_id', null, false);
        if ($researchId > 0) {
            $builder->join('research_documents rd', 'rd.document_id = o.source_document_id')
                ->where('rd.research_id', $researchId);
        }
        $rows = $builder->groupBy('o.entity_id, o.source_document_id')->get()->getResultArray();
        return array_map(static fn(array $row): array => [
            'id' => 'documentary-' . $row['entity_id'] . '-' . $row['source_document_id'],
            'source_entity_id' => $row['entity_id'],
            'target_entity_id' => $row['source_document_id'],
            'kind' => 'documentary', 'status' => 'hypothesis',
            'occurrences' => (int) $row['occurrences'],
        ], $rows);
    }
}
