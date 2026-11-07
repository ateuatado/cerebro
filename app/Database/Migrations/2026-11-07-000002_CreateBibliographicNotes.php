<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBibliographicNotes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'work_id' => ['type' => 'INTEGER'],
            'locator' => ['type' => 'VARCHAR', 'constraint' => 255],
            'excerpt' => ['type' => 'TEXT'],
            'observations' => ['type' => 'TEXT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('work_id');
        $this->forge->addForeignKey('work_id', 'bibliographic_works', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('bibliographic_notes');
    }

    public function down()
    {
        $this->forge->dropTable('bibliographic_notes');
    }
}
