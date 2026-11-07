<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentNotes extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE document_notes (
            id SERIAL PRIMARY KEY,
            document_id INTEGER REFERENCES entities(id) ON DELETE SET NULL,
            document_label TEXT NOT NULL,
            kind VARCHAR(20) NOT NULL CHECK (kind IN ('excerto', 'parafrase', 'comentario')),
            locator VARCHAR(255) NOT NULL,
            excerpt TEXT NOT NULL,
            observations TEXT NOT NULL,
            CHECK (excerpt = '' OR locator <> '')
        )");
        $this->db->query('CREATE INDEX document_notes_document_idx ON document_notes(document_id)');
        $this->db->query('ALTER TABLE research_diary
            ADD COLUMN document_id INTEGER REFERENCES entities(id) ON DELETE SET NULL,
            ADD COLUMN document_note_id INTEGER REFERENCES document_notes(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE research_diary DROP COLUMN document_id, DROP COLUMN document_note_id');
        $this->forge->dropTable('document_notes');
    }
}
