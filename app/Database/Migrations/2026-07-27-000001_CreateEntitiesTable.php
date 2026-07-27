<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEntitiesTable extends Migration
{
    public function up(): void
    {
        // 3.1 Enum: entity_type
        $this->db->query("
            CREATE TYPE entity_type AS ENUM (
                'person',
                'location',
                'event',
                'document'
            )
        ");

        // 3.2 Enum: entity_status
        $this->db->query("
            CREATE TYPE entity_status AS ENUM (
                'confirmed',
                'hypothesis'
            )
        ");

        // 3.3 Tabela: entities
        $this->db->query("
            CREATE TABLE entities (
                id              SERIAL PRIMARY KEY,
                type            entity_type NOT NULL,
                name            TEXT NOT NULL,
                attributes      JSONB NOT NULL DEFAULT '{}',
                status          entity_status NOT NULL DEFAULT 'hypothesis',
                created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by      TEXT,
                validated_by    TEXT,
                CONSTRAINT confirmed_requires_validation
                    CHECK (status <> 'confirmed' OR validated_by IS NOT NULL)
            )
        ");

        // Índices
        $this->db->query("CREATE INDEX idx_entities_type ON entities (type)");
        $this->db->query("CREATE INDEX idx_entities_status ON entities (status)");
        $this->db->query("CREATE INDEX idx_entities_type_status ON entities (type, status)");
        $this->db->query("CREATE INDEX idx_entities_attributes ON entities USING GIN (attributes)");

        // 3.4 Views por tipo
        $this->db->query("
            CREATE VIEW entity_persons AS
                SELECT * FROM entities WHERE type = 'person'
        ");
        $this->db->query("
            CREATE VIEW entity_locations AS
                SELECT * FROM entities WHERE type = 'location'
        ");
        $this->db->query("
            CREATE VIEW entity_events AS
                SELECT * FROM entities WHERE type = 'event'
        ");
        $this->db->query("
            CREATE VIEW entity_documents AS
                SELECT * FROM entities WHERE type = 'document'
        ");

        // 3.5 Views por status
        $this->db->query("
            CREATE VIEW confirmed_entities AS
                SELECT * FROM entities WHERE status = 'confirmed'
        ");
        $this->db->query("
            CREATE VIEW hypothesis_entities AS
                SELECT * FROM entities WHERE status = 'hypothesis'
        ");

        // 3.8 Trigger: updated_at
        $this->db->query("
            CREATE OR REPLACE FUNCTION update_timestamp()
            RETURNS TRIGGER AS \$\$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");
        $this->db->query("
            CREATE TRIGGER trg_entities_updated_at
                BEFORE UPDATE ON entities
                FOR EACH ROW EXECUTE FUNCTION update_timestamp()
        ");
    }

    public function down(): void
    {
        // Desfaz na ordem: triggers → views → tabela → ENUMs

        $this->db->query("DROP TRIGGER IF EXISTS trg_entities_updated_at ON entities");
        $this->db->query("DROP FUNCTION IF EXISTS update_timestamp()");

        $this->db->query("DROP VIEW IF EXISTS hypothesis_entities");
        $this->db->query("DROP VIEW IF EXISTS confirmed_entities");

        $this->db->query("DROP VIEW IF EXISTS entity_documents");
        $this->db->query("DROP VIEW IF EXISTS entity_events");
        $this->db->query("DROP VIEW IF EXISTS entity_locations");
        $this->db->query("DROP VIEW IF EXISTS entity_persons");

        $this->db->query("DROP TABLE IF EXISTS entities");

        $this->db->query("DROP TYPE IF EXISTS entity_status");
        $this->db->query("DROP TYPE IF EXISTS entity_type");
    }
}
