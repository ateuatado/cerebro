<?php

namespace App\Services;

use Config\Database;

class EntityIdentityService
{
    public function findForExtraction(string $type, string $name): ?array
    {
        $scope = $type === 'document' ? "type = 'document'" : "type <> 'document'";
        $rows = Database::connect()->query(
            "SELECT * FROM entities WHERE {$scope}
             AND lower(regexp_replace(btrim(name), '\\s+', ' ', 'g')) = ? ORDER BY id",
            [$this->normalize($name)]
        )->getResultArray();
        if (count($rows) > 1) {
            throw new \RuntimeException('Nome ambíguo: ' . $name . '. Revise as identidades antes de reprocessar.');
        }
        return $rows[0] ?? null;
    }

    public function normalize(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        return mb_strtolower($name, 'UTF-8');
    }

    public function findExisting(string $type, string $name): ?array
    {
        $normalized = $this->normalize($name);
        if ($normalized === '') {
            return null;
        }

        $row = Database::connect()->query(
            "SELECT * FROM entities
             WHERE type = ?
               AND lower(regexp_replace(btrim(name), '\\s+', ' ', 'g')) = ?
             ORDER BY CASE WHEN status = 'confirmed' THEN 0 ELSE 1 END, id ASC
             LIMIT 1",
            [$type, $normalized]
        )->getRowArray();

        return $row ?: null;
    }
}
