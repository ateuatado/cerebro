<?php

namespace App\Services;

use Config\Database;

class EntityIdentityService
{
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
