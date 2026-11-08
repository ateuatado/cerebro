<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductBibliography extends Migration
{
    public function up(): void
    {
        $this->db->query('CREATE TABLE intellectual_product_works (
            product_id INTEGER NOT NULL REFERENCES intellectual_products(id) ON DELETE CASCADE,
            work_id INTEGER NOT NULL REFERENCES bibliographic_works(id) ON DELETE RESTRICT,
            PRIMARY KEY (product_id, work_id)
        )');
        $this->db->query('CREATE INDEX intellectual_product_works_work_idx ON intellectual_product_works(work_id)');
    }

    public function down(): void
    {
        $this->forge->dropTable('intellectual_product_works');
    }
}
