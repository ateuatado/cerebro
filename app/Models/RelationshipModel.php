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
     * Retorna somente os campos necessários para desenhar as arestas.
     */
    public function findGraphEdges(): array
    {
        return $this->db->table('relationships')
            ->select('id, source_entity_id, target_entity_id, relationship_type, direction, confidence, status, source_document_id')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
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

    /**
     * Busca relações cujos documentos de origem pertencem a uma pesquisa específica.
     */
    public function findByResearch(int $researchId): array
    {
        return $this->db->table('relationships r')
            ->select('r.id, r.source_entity_id, r.target_entity_id, r.relationship_type, r.direction, r.confidence, r.status, r.source_document_id')
            ->join('research_documents rd', 'rd.document_id = r.source_document_id')
            ->where('rd.research_id', $researchId)
            ->get()
            ->getResultArray();
    }
}
