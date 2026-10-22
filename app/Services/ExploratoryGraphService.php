<?php

namespace App\Services;

/** Filtra apenas a projeção visual; não altera fontes ou relações armazenadas. */
class ExploratoryGraphService
{
    public function entities(array $entities): array
    {
        return array_values(array_filter($entities, static fn(array $entity): bool =>
            !in_array($entity['graph_hidden'] ?? false, [true, 1, '1', 't', 'true'], true)));
    }

    public function relationships(array $entities, array $relationships): array
    {
        $types = array_column($entities, 'type', 'id');
        $visibleIds = array_fill_keys(array_column($this->entities($entities), 'id'), true);
        return array_values(array_filter($relationships, static function (array $relation) use ($types, $visibleIds): bool {
            if (($relation['kind'] ?? '') === 'documentary') return false;
            $source = (string) $relation['source_entity_id'];
            $target = (string) $relation['target_entity_id'];
            if (!isset($visibleIds[$source], $visibleIds[$target])) return false;
            $document = (string) ($relation['source_document_id'] ?? '');
            if ($document !== '' && ($source === $document || $target === $document)) return false;

            // A IA também pode representar a fonte como outro nó documental.
            // Menções documentais não expressam interconexões históricas.
            $mention = in_array(mb_strtolower(trim($relation['relationship_type'] ?? '')), [
                'menciona', 'mencionou', 'mencionado_em', 'mencionada_em', 'documenta',
            ], true);
            return !($mention && (($types[$source] ?? '') === 'document' || ($types[$target] ?? '') === 'document'));
        }));
    }
}
