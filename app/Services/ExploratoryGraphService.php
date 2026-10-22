<?php

namespace App\Services;

/** Filtra apenas a projeção visual; não altera fontes ou relações armazenadas. */
class ExploratoryGraphService
{
    public function relationships(array $entities, array $relationships): array
    {
        $types = array_column($entities, 'type', 'id');
        return array_values(array_filter($relationships, static function (array $relation) use ($types): bool {
            if (($relation['kind'] ?? '') === 'documentary') return false;
            $source = (string) $relation['source_entity_id'];
            $target = (string) $relation['target_entity_id'];
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
