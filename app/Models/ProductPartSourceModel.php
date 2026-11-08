<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductPartSourceModel extends Model
{
    protected $table = 'intellectual_part_sources';

    public function sources(int $productId, ?int $partId = null): array
    {
        $builder = $this->db->table('intellectual_part_sources link')
            ->select('link.*, part.title AS part_title, note.locator,
                COALESCE(document.id, note_document.id) AS original_id,
                COALESCE(document.name, note_document.name, note.document_label) AS source_title', false)

            ->join('intellectual_product_parts part', 'part.id = link.part_id')
            ->join('document_notes note', 'note.id = link.note_id', 'left')
            ->join('entities document', 'document.id = link.document_id', 'left')
            ->join('entities note_document', 'note_document.id = note.document_id', 'left')
            ->where('link.product_id', $productId);
        if ($partId !== null) {
            $builder->where('link.part_id', $partId);
        }

        return $builder->orderBy('part.title')->orderBy('source_title')->orderBy('link.id')->get()->getResultArray();
    }

    public function attach(int $productId, int $partId, string $kind, int $targetId): bool
    {
        if ($kind === 'document') {
            $column = 'document_id';
            $target = "entities item ON item.id = ? AND item.type = 'document'";
        } elseif ($kind === 'note') {
            $column = 'note_id';
            $target = 'document_notes item ON item.id = ? AND item.document_id IS NOT NULL';
        } else {
            return false;
        }

        $saved = $this->db->query("INSERT INTO intellectual_part_sources (product_id, part_id, $column)
            SELECT part.product_id, part.id, item.id FROM intellectual_product_parts part
            JOIN $target WHERE part.product_id = ? AND part.id = ?
            ON CONFLICT (part_id, $column) DO NOTHING", [$targetId, $productId, $partId]);

        return $saved === true && $this->builder()->where([
            'product_id' => $productId, 'part_id' => $partId, $column => $targetId,
        ])->countAllResults() > 0;
    }

    public function detach(int $productId, int $partId, int $linkId): bool
    {
        return $this->builder()->where(['product_id' => $productId, 'part_id' => $partId, 'id' => $linkId])->delete()
            && $this->db->affectedRows() === 1;
    }
}
