<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * EntityAttributeVocabularyModel — Spec 11
 *
 * Gerencia o vocabulário controlado de chaves de atributos JSONB por tipo de entidade.
 * Usado para guiar prompts de IA e popular dropdowns na modal de aprovação.
 */
class EntityAttributeVocabularyModel extends Model
{
    protected $table      = 'entity_attribute_vocabulary';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'entity_type',
        'key_name',
        'label',
        'description',
        'is_active',
    ];

    /** Tipos válidos — espelha o ENUM entity_type do banco */
    public const VALID_TYPES = ['person', 'location', 'event', 'document'];

    /**
     * Retorna o vocabulário ativo para um tipo de entidade.
     * Ordenado por key_name para exibição consistente.
     */
    public function getByType(string $entityType): array
    {
        return $this->where('entity_type', $entityType)
                    ->where('is_active', true)
                    ->orderBy('key_name', 'ASC')
                    ->findAll();
    }

    /**
     * Retorna todos os vocabulários ativos agrupados por tipo.
     * Formato: ['person' => [...], 'location' => [...], ...]
     * Usado pelo endpoint de API para o front-end.
     */
    public function getAllGroupedByType(): array
    {
        $all = $this->where('is_active', true)
                    ->orderBy('entity_type', 'ASC')
                    ->orderBy('key_name', 'ASC')
                    ->findAll();

        $grouped = [];
        foreach (self::VALID_TYPES as $type) {
            $grouped[$type] = [];
        }
        foreach ($all as $row) {
            $type = $row['entity_type'];
            if (isset($grouped[$type])) {
                $grouped[$type][] = [
                    'key'   => $row['key_name'],
                    'label' => $row['label'],
                    'desc'  => $row['description'] ?? '',
                ];
            }
        }
        return $grouped;
    }

    /**
     * Formata o vocabulário de um tipo como texto para inserção no prompt da IA.
     * Exemplo de saída:
     *   patente (Patente / Graduação), cargo (Cargo ou Função), ...
     */
    public function getAsPromptList(string $entityType): string
    {
        $rows = $this->getByType($entityType);
        if (empty($rows)) {
            return '';
        }
        $parts = array_map(
            fn($r) => "{$r['key_name']} ({$r['label']})",
            $rows
        );
        return implode(', ', $parts);
    }

    /**
     * Adiciona uma nova chave ao vocabulário.
     * Retorna false se a combinação type+key já existir.
     */
    public function addKey(string $entityType, string $keyName, string $label, string $description = ''): bool
    {
        if (!in_array($entityType, self::VALID_TYPES, true)) {
            return false;
        }

        $exists = $this->where('entity_type', $entityType)
                       ->where('key_name', $keyName)
                       ->first();
        if ($exists) {
            return false;
        }

        $this->insert([
            'entity_type' => $entityType,
            'key_name'    => $keyName,
            'label'       => $label,
            'description' => $description,
            'is_active'   => true,
        ]);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Verifica se um tipo de entidade é válido conforme o ENUM do banco.
     * Uso defensivo no controller antes de qualquer query.
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::VALID_TYPES, true);
    }
}
