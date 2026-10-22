<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUnresolvedRelationships extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE unresolved_relationships (
            id BIGSERIAL PRIMARY KEY,
            source_document_id INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
            suggestion JSONB NOT NULL,
            reason TEXT NOT NULL,
            status entity_status NOT NULL DEFAULT 'hypothesis' CHECK (status = 'hypothesis'),
            fingerprint TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            UNIQUE(source_document_id, fingerprint)
        )");
        $this->db->query('CREATE TRIGGER trg_unresolved_source_is_document
            BEFORE INSERT OR UPDATE ON unresolved_relationships
            FOR EACH ROW EXECUTE FUNCTION check_source_is_document()');
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS unresolved_relationships');
    }
}
