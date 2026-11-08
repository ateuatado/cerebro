<?php

namespace App\Models;

use CodeIgniter\Model;

class PartBibliographyModel extends Model
{
    protected $table = 'intellectual_part_works';

    public function workIds(int $productId, int $partId): array
    {
        return array_column($this->builder()->select('work_id')->where('product_id', $productId)
            ->where('part_id', $partId)->get()->getResultArray(), 'work_id');
    }

    public function partsByWork(int $productId): array
    {
        $rows = $this->builder()->select('intellectual_part_works.work_id, intellectual_product_parts.id, intellectual_product_parts.title')
            ->join('intellectual_product_parts', 'intellectual_product_parts.id = intellectual_part_works.part_id')
            ->where('intellectual_part_works.product_id', $productId)
            ->orderBy('intellectual_product_parts.title')->orderBy('intellectual_product_parts.id')->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['work_id']][] = $row;
        }

        return $result;
    }

    public function attach(int $productId, int $partId, int $workId): bool
    {
        return $this->db->query('INSERT INTO intellectual_part_works (product_id, part_id, work_id)
            SELECT part.product_id, part.id, selected.work_id FROM intellectual_product_parts part
            JOIN intellectual_product_works selected ON selected.product_id = part.product_id
            WHERE part.product_id = ? AND part.id = ? AND selected.work_id = ?
            ON CONFLICT (part_id, work_id) DO NOTHING', [$productId, $partId, $workId]) === true
            && $this->builder()->where([
                'product_id' => $productId, 'part_id' => $partId, 'work_id' => $workId,
            ])->countAllResults() > 0;
    }

    public function detach(int $productId, int $partId, int $workId): bool
    {
        return $this->builder()->where([
            'product_id' => $productId, 'part_id' => $partId, 'work_id' => $workId,
        ])->delete();
    }
}
