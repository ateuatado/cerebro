<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductPartSources extends Migration
{
    public function up(): void
    {
        $this->db->query('CREATE TABLE intellectual_part_sources (
            id SERIAL PRIMARY KEY,
            product_id INTEGER NOT NULL,
            part_id INTEGER NOT NULL,
            document_id INTEGER REFERENCES entities(id) ON DELETE CASCADE,
            note_id INTEGER REFERENCES document_notes(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id, part_id) REFERENCES intellectual_product_parts(product_id, id) ON DELETE CASCADE,
            CHECK ((document_id IS NULL) <> (note_id IS NULL)),
            UNIQUE (part_id, document_id),
            UNIQUE (part_id, note_id)
        )');
        $this->db->query('CREATE INDEX intellectual_part_sources_product_idx ON intellectual_part_sources(product_id)');
        $this->db->query('CREATE INDEX intellectual_part_sources_document_idx ON intellectual_part_sources(document_id)');
        $this->db->query('CREATE INDEX intellectual_part_sources_note_idx ON intellectual_part_sources(note_id)');
    }

    public function down(): void
    {
        $this->forge->dropTable('intellectual_part_sources');
    }
}
