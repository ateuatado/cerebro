<?php

namespace App\Models;

use CodeIgniter\Model;

class ResearchProjectModel extends Model
{
    protected $table            = 'research_projects';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'title',
        'description',
        'created_by',
    ];

    /**
     * Retorna todas as pesquisas acompanhadas da contagem de documentos associados.
     */
    public function findAllWithDocumentCount(): array
    {
        return $this->db->table('research_projects rp')
            ->select('rp.*, COUNT(rd.document_id) AS document_count')
            ->join('research_documents rd', 'rd.research_id = rp.id', 'left')
            ->groupBy('rp.id')
            ->orderBy('rp.updated_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Associa um documento a uma pesquisa.
     */
    public function attachDocument(int $researchId, int $documentId): bool
    {
        $builder = $this->db->table('research_documents');
        $exists = $builder->where([
            'research_id' => $researchId,
            'document_id' => $documentId,
        ])->countAllResults();

        if ($exists === 0) {
            return $builder->insert([
                'research_id' => $researchId,
                'document_id' => $documentId,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    /**
     * Desvincula um documento de uma pesquisa.
     */
    public function detachDocument(int $researchId, int $documentId): bool
    {
        return $this->db->table('research_documents')
            ->where([
                'research_id' => $researchId,
                'document_id' => $documentId,
            ])
            ->delete();
    }

    /**
     * Sincroniza a lista de documentos de uma pesquisa.
     */
    public function syncDocuments(int $researchId, array $documentIds): void
    {
        $this->db->table('research_documents')
            ->where('research_id', $researchId)
            ->delete();

        foreach ($documentIds as $docId) {
            $docIdInt = (int)$docId;
            if ($docIdInt > 0) {
                $this->attachDocument($researchId, $docIdInt);
            }
        }
    }

    /**
     * Retorna todos os documentos (entidades do tipo 'document') vinculados a uma pesquisa.
     */
    public function getDocuments(int $researchId): array
    {
        return $this->db->table('entities e')
            ->select('e.*, rd.created_at AS attached_at')
            ->join('research_documents rd', 'rd.document_id = e.id')
            ->where('rd.research_id', $researchId)
            ->where('e.type', 'document')
            ->orderBy('e.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Retorna todas as pesquisas vinculadas a um determinado documento.
     */
    public function getResearchesForDocument(int $documentId): array
    {
        return $this->db->table('research_projects rp')
            ->select('rp.*')
            ->join('research_documents rd', 'rd.research_id = rp.id')
            ->where('rd.document_id', $documentId)
            ->orderBy('rp.title', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Retorna os IDs de todos os documentos de uma determinada pesquisa.
     */
    public function getDocumentIds(int $researchId): array
    {
        $rows = $this->db->table('research_documents')
            ->select('document_id')
            ->where('research_id', $researchId)
            ->get()
            ->getResultArray();

        return array_column($rows, 'document_id');
    }
}
