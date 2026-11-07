<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateIntellectualProducts extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE intellectual_products (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            kind VARCHAR(20) NOT NULL CHECK (kind IN ('tese', 'dissertacao', 'artigo', 'ensaio', 'apresentacao'))
        )");
        $this->db->query("CREATE TABLE intellectual_product_parts (
            id SERIAL PRIMARY KEY,
            product_id INTEGER NOT NULL REFERENCES intellectual_products(id) ON DELETE CASCADE,
            parent_id INTEGER,
            position INTEGER NOT NULL CHECK (position > 0),
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL DEFAULT '',
            UNIQUE (product_id, id),
            FOREIGN KEY (product_id, parent_id) REFERENCES intellectual_product_parts(product_id, id)
        )");
        $this->db->query('CREATE INDEX intellectual_product_parts_order_idx ON intellectual_product_parts(product_id, position)');
    }

    public function down(): void
    {
        $this->forge->dropTable('intellectual_product_parts');
        $this->forge->dropTable('intellectual_products');
    }
}
