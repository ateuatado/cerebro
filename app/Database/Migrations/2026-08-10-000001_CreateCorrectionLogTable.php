<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateCorrectionLogTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'auto_increment' => true,
            ],
            'entity_id' => [
                'type'       => 'INTEGER',
                'null'       => false,
            ],
            'field' => [
                'type'       => 'TEXT',
                'null'       => false,
                'comment'    => 'Campo corrigido: name, type, ou attribute:{key}',
            ],
            'old_value' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
            'new_value' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
            'corrected_by' => [
                'type'       => 'INTEGER',
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'TIMESTAMPTZ',
                'null'       => false,
                'default'    => new RawSql('NOW()'),
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('entity_id', 'entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('corrected_by', 'users', 'id', 'SET NULL', 'CASCADE');

        // Índice para buscar correções recentes por documento fonte
        // (útil para construir prompts de feedback da IA)
        $this->forge->createTable('correction_log', true);

        // Criar índice via SQL puro para a consulta de correlação
        // entity_id → entities → relationships → source_document_id
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_correction_log_entity 
                          ON correction_log (entity_id)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_correction_log_created 
                          ON correction_log (created_at DESC)');
    }

    public function down()
    {
        $this->forge->dropTable('correction_log', true);
    }
}
