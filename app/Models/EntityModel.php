<?php

namespace App\Models;

use CodeIgniter\Model;

class EntityModel extends Model
{
    protected $table            = 'entities';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'type',
        'name',
        'attributes',
        'status',
        'created_by',
        'validated_by',
        'graph_hidden',
    ];

    public function setGraphHidden(int $id, bool $hidden): bool
    {
        return $this->update($id, ['graph_hidden' => $hidden ? 't' : 'f']);
    }

    /**
     * Filtra entidades por tipo.
     */
    public function findByType(string $type): array
    {
        return $this->where('type', $type)->findAll();
    }

    /**
     * Consulta apenas entidades confirmadas (view confirmed_entities).
     */
    public function findConfirmed(): array
    {
        return $this->db->table('confirmed_entities')->get()->getResultArray();
    }

    /**
     * Consulta apenas hipóteses (view hypothesis_entities).
     */
    public function findHypothesis(): array
    {
        return $this->db->table('hypothesis_entities')->get()->getResultArray();
    }

    /**
     * SELECT direto na tabela entities, sem filtro de status.
     * Nome explícito: deixa claro que não há distinção fato/hipótese.
     */
    public function findAllRaw(): array
    {
        return $this->db->table('entities')->get()->getResultArray();
    }

    /**
     * Retorna somente os campos necessários para desenhar o grafo.
     * Não carregar attributes JSONB reduz transferência e memória.
     */
    public function findGraphNodes(): array
    {
        return $this->db->table('entities')
            ->select('id, type, name, status, graph_hidden')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Busca todas as entidades (nós) associadas a uma pesquisa:
     * - Documentos vinculados diretamente à pesquisa.
     * - Entidades presentes em relações lastreadas por documentos da pesquisa.
     */
    public function findByResearch(int $researchId): array
    {
        $sql = "
            SELECT e.id, e.type, e.name, e.status, e.graph_hidden
            FROM entities e
            WHERE e.id IN (
                SELECT document_id FROM research_documents WHERE research_id = ?
                UNION
                SELECT r.source_entity_id FROM relationships r
                JOIN research_documents rd ON rd.document_id = r.source_document_id WHERE rd.research_id = ?
                UNION
                SELECT r.target_entity_id FROM relationships r
                JOIN research_documents rd ON rd.document_id = r.source_document_id WHERE rd.research_id = ?
                UNION
                SELECT o.entity_id FROM entity_observations o
                JOIN research_documents rd ON rd.document_id = o.source_document_id WHERE rd.research_id = ?
            )
            ORDER BY e.name ASC
        ";

        return $this->db->query($sql, [$researchId, $researchId, $researchId, $researchId])->getResultArray();
    }
}
