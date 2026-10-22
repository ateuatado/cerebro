<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEntityObservations extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE entity_observations (
            id BIGSERIAL PRIMARY KEY,
            entity_id INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
            source_document_id INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
            suggested_type entity_type NOT NULL,
            attributes JSONB NOT NULL DEFAULT '{}',
            source_reference JSONB NOT NULL DEFAULT '{}',
            status entity_status NOT NULL DEFAULT 'hypothesis' CHECK (status = 'hypothesis'),
            fingerprint TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            UNIQUE (entity_id, source_document_id, fingerprint)
        )");
        $this->db->query('CREATE INDEX idx_observations_document ON entity_observations(source_document_id)');
        $this->db->query('CREATE TRIGGER trg_observations_source_is_document
            BEFORE INSERT OR UPDATE ON entity_observations
            FOR EACH ROW EXECUTE FUNCTION check_source_is_document()');
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS entity_observations');
    }
}
