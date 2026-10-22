<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEntityGraphVisibility extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE entities ADD COLUMN graph_hidden BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE entities DROP COLUMN graph_hidden');
    }
}
