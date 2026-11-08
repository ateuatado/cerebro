<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePartBibliography extends Migration
{
    public function up(): void
    {
        $this->db->query('CREATE TABLE intellectual_part_works (
            product_id INTEGER NOT NULL,
            part_id INTEGER NOT NULL,
            work_id INTEGER NOT NULL,
            PRIMARY KEY (part_id, work_id),
            FOREIGN KEY (product_id, part_id) REFERENCES intellectual_product_parts(product_id, id) ON DELETE CASCADE,
            FOREIGN KEY (product_id, work_id) REFERENCES intellectual_product_works(product_id, work_id) ON DELETE RESTRICT
        )');
        $this->db->query('CREATE INDEX intellectual_part_works_product_work_idx ON intellectual_part_works(product_id, work_id)');
    }

    public function down(): void
    {
        $this->forge->dropTable('intellectual_part_works');
    }
}
