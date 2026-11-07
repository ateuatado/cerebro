<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResearchDiary extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'activity_date' => ['type' => 'DATE'],
            'account' => ['type' => 'TEXT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('activity_date');
        $this->forge->createTable('research_diary');
    }

    public function down()
    {
        $this->forge->dropTable('research_diary');
    }
}
