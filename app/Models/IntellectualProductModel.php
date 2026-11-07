<?php

namespace App\Models;

use CodeIgniter\Model;

class IntellectualProductModel extends Model
{
    protected $table = 'intellectual_products';
    protected $allowedFields = ['title', 'kind'];

    public const KINDS = [
        'tese' => 'Tese',
        'dissertacao' => 'Dissertação',
        'artigo' => 'Artigo',
        'ensaio' => 'Ensaio',
        'apresentacao' => 'Apresentação',
    ];

    public function createWithOutline(string $title, string $kind): ?int
    {
        $outline = match ($kind) {
            'tese', 'dissertacao' => ['Introdução' => [], 'Capítulo 1' => ['1.1 — Primeiro subcapítulo'], 'Conclusão' => []],
            'artigo' => ['Introdução' => [], 'Metodologia' => [], 'Resultados e discussão' => [], 'Conclusão' => []],
            'ensaio' => ['Introdução' => [], 'Desenvolvimento' => [], 'Considerações finais' => []],
            'apresentacao' => ['Abertura' => [], 'Desenvolvimento' => [], 'Encerramento' => []],
        };

        $this->db->transStart();
        $id = $this->insert(['title' => $title, 'kind' => $kind]);
        if ($id !== false) {
            $position = 1;
            foreach ($outline as $heading => $children) {
                $parent = $this->insertPart((int) $id, null, $position++, $heading);
                foreach ($children as $index => $child) {
                    if ($parent !== null) {
                        $this->insertPart((int) $id, $parent, $index + 1, $child);
                    }
                }
            }
        }
        if ($id === false) {
            $this->db->transRollback();

            return null;
        }

        return $this->db->transComplete() ? (int) $id : null;
    }

    public function parts(int $id): array
    {
        return $this->db->table('intellectual_product_parts')->where('product_id', $id)
            ->orderBy('position')->orderBy('id')->get()->getResultArray();
    }

    public function savePart(int $productId, ?int $partId, ?int $parentId, string $title, string $content): ?int
    {
        return $this->changeOutline($productId, function () use ($productId, $partId, $parentId, $title, $content) {
            $data = ['title' => $title, 'content' => $content];
            if ($partId !== null) {
                if ($this->part($productId, $partId) === null) {
                    return null;
                }

                return $this->partBuilder($productId)->where('id', $partId)->update($data) ? $partId : null;
            }
            if ($parentId !== null) {
                $parent = $this->part($productId, $parentId);
                if ($parent === null || $parent['parent_id'] !== null) {
                    return null;
                }
            }
            $last = $this->partBuilder($productId)->where('parent_id', $parentId)
                ->selectMax('position')->get()->getRowArray();
            $saved = $this->db->table('intellectual_product_parts')->insert($data + [
                'product_id' => $productId, 'parent_id' => $parentId, 'position' => ((int) ($last['position'] ?? 0)) + 1,
            ]);

            return $saved ? (int) $this->db->insertID() : null;
        });
    }

    public function movePart(int $productId, int $partId, string $direction): bool
    {
        return $this->changeOutline($productId, function () use ($productId, $partId, $direction) {
            $part = $this->part($productId, $partId);
            if ($part === null) {
                return null;
            }
            $siblings = $this->partBuilder($productId)->where('parent_id', $part['parent_id'])
                ->orderBy('position')->orderBy('id')->get()->getResultArray();
            $index = array_search((string) $partId, array_map('strval', array_column($siblings, 'id')), true);
            $other = $siblings[$index + ($direction === 'up' ? -1 : 1)] ?? null;
            if ($other === null) {
                return null;
            }

            return $this->partBuilder($productId)->where('id', $partId)->update(['position' => $other['position']])
                && $this->partBuilder($productId)->where('id', $other['id'])->update(['position' => $part['position']])
                ? true : null;
        }) === true;
    }

    public function deletePart(int $productId, int $partId): bool
    {
        return $this->changeOutline($productId, function () use ($productId, $partId) {
            if ($this->part($productId, $partId) === null
                || $this->partBuilder($productId)->where('parent_id', $partId)->countAllResults() > 0) {
                return null;
            }

            return $this->partBuilder($productId)->where('id', $partId)->delete() ? true : null;
        }) === true;
    }

    private function changeOutline(int $productId, callable $change): int|bool|null
    {
        $this->db->transStart();
        $product = $this->db->query('SELECT id FROM intellectual_products WHERE id = ? FOR UPDATE', [$productId])->getRowArray();
        $result = $product === null ? null : $change();
        if ($result === null) {
            $this->db->transRollback();

            return null;
        }

        return $this->db->transComplete() ? $result : null;
    }

    private function part(int $productId, int $partId): ?array
    {
        return $this->partBuilder($productId)->where('id', $partId)->get()->getRowArray();
    }

    private function partBuilder(int $productId): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('intellectual_product_parts')->where('product_id', $productId);
    }

    private function insertPart(int $productId, ?int $parentId, int $position, string $title): ?int
    {
        $saved = $this->db->table('intellectual_product_parts')->insert([
            'product_id' => $productId, 'parent_id' => $parentId, 'position' => $position, 'title' => $title,
        ]);

        return $saved ? (int) $this->db->insertID() : null;
    }
}
