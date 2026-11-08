<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductBibliographyModel extends Model
{
    protected $table = 'intellectual_product_works';
    protected $primaryKey = 'product_id';
    protected $useAutoIncrement = false;

    public function works(int $productId): array
    {
        return $this->builder()->select('bibliographic_works.*')
            ->join('bibliographic_works', 'bibliographic_works.id = intellectual_product_works.work_id')
            ->where('product_id', $productId)->orderBy('bibliographic_works.title')
            ->orderBy('bibliographic_works.id')->get()->getResultArray();
    }

    public function attach(int $productId, int $workId): bool
    {
        return $this->db->query('INSERT INTO intellectual_product_works (product_id, work_id)
            SELECT product.id, work.id FROM intellectual_products product CROSS JOIN bibliographic_works work
            WHERE product.id = ? AND work.id = ? ON CONFLICT (product_id, work_id) DO NOTHING', [$productId, $workId]) === true;
    }

    public function detach(int $productId, int $workId): bool
    {
        return $this->builder()->where('product_id', $productId)->where('work_id', $workId)->delete();
    }

    public function hasWork(int $workId): bool
    {
        return $this->builder()->where('work_id', $workId)->countAllResults() > 0;
    }
}
