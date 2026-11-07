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

    private function insertPart(int $productId, ?int $parentId, int $position, string $title): ?int
    {
        $saved = $this->db->table('intellectual_product_parts')->insert([
            'product_id' => $productId, 'parent_id' => $parentId, 'position' => $position, 'title' => $title,
        ]);

        return $saved ? (int) $this->db->insertID() : null;
    }
}
