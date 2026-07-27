<?php

namespace App\Models;

use CodeIgniter\Model;

class RelationshipModel extends Model
{
    protected $table            = 'relationships';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'source_entity_id',
        'target_entity_id',
        'relationship_type',
        'direction',
        'confidence',
        'source_document_id',
        'source_reference',
        'status',
        'created_by',
        'validated_by',
    ];

    /**
     * Consulta apenas relações confirmadas (view confirmed_relationships).
     */
    public function findConfirmed(): array
    {
        return $this->db->table('confirmed_relationships')->get()->getResultArray();
    }

    /**
     * Consulta apenas hipóteses (view hypothesis_relationships).
     */
    public function findHypothesis(): array
    {
        return $this->db->table('hypothesis_relationships')->get()->getResultArray();
    }

    /**
     * SELECT direto na tabela relationships, sem filtro de status.
     */
    public function findAllRaw(): array
    {
        return $this->db->table('relationships')->get()->getResultArray();
    }

    /**
     * Busca relações onde a entidade é origem.
     */
    public function findBySource(int $entityId): array
    {
        return $this->where('source_entity_id', $entityId)->findAll();
    }

    /**
     * Busca relações onde a entidade é destino.
     */
    public function findByTarget(int $entityId): array
    {
        return $this->where('target_entity_id', $entityId)->findAll();
    }

    /**
     * Busca relações lastreadas por um documento específico.
     */
    public function findByDocument(int $documentId): array
    {
        return $this->where('source_document_id', $documentId)->findAll();
    }
}
