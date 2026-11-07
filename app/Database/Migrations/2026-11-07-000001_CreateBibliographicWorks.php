<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBibliographicWorks extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 40],
            'authors' => ['type' => 'TEXT', 'null' => true],
            'publication_date' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'publication' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'identifier' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('bibliographic_works');
    }

    public function down()
    {
        $this->forge->dropTable('bibliographic_works');
    }
}
