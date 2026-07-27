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
    ];

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
}
