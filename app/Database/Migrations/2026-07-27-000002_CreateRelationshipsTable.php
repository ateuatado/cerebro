<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRelationshipsTable extends Migration
{
    public function up(): void
    {
        // 3.6 Tabela: relationships
        $this->db->query("
            CREATE TABLE relationships (
                id                  SERIAL PRIMARY KEY,
                source_entity_id    INTEGER NOT NULL REFERENCES entities(id),
                target_entity_id    INTEGER NOT NULL REFERENCES entities(id),
                relationship_type   TEXT NOT NULL,
                direction           TEXT NOT NULL DEFAULT 'directed'
                                    CHECK (direction IN ('directed', 'symmetric')),
                confidence          REAL NOT NULL DEFAULT 0.0
                                    CHECK (confidence >= 0.0 AND confidence <= 1.0),
                source_document_id  INTEGER NOT NULL REFERENCES entities(id),
                source_reference    JSONB NOT NULL DEFAULT '{}',
                status              entity_status NOT NULL DEFAULT 'hypothesis',
                created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by          TEXT,
                validated_by        TEXT,
                CONSTRAINT different_entities
                    CHECK (source_entity_id <> target_entity_id),
                CONSTRAINT confirmed_requires_validation
                    CHECK (status <> 'confirmed' OR validated_by IS NOT NULL)
            )
        ");

        // Índices
        $this->db->query("CREATE INDEX idx_rel_source ON relationships (source_entity_id)");
        $this->db->query("CREATE INDEX idx_rel_target ON relationships (target_entity_id)");
        $this->db->query("CREATE INDEX idx_rel_type ON relationships (relationship_type)");
        $this->db->query("CREATE INDEX idx_rel_status ON relationships (status)");
        $this->db->query("CREATE INDEX idx_rel_document ON relationships (source_document_id)");
        $this->db->query("CREATE INDEX idx_rel_source_target ON relationships (source_entity_id, target_entity_id)");

        // 3.7 Views por status
        $this->db->query("
            CREATE VIEW confirmed_relationships AS
                SELECT * FROM relationships WHERE status = 'confirmed'
        ");
        $this->db->query("
            CREATE VIEW hypothesis_relationships AS
                SELECT * FROM relationships WHERE status = 'hypothesis'
        ");

        // 3.8 Trigger updated_at para relationships
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
            CREATE TRIGGER trg_relationships_updated_at
                BEFORE UPDATE ON relationships
                FOR EACH ROW EXECUTE FUNCTION update_timestamp()
        ");

        // 3.8 Função + trigger: source_document_id deve apontar para documento
        $this->db->query("
            CREATE OR REPLACE FUNCTION check_source_is_document()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM entities
                    WHERE id = NEW.source_document_id AND type = 'document'
                ) THEN
                    RAISE EXCEPTION
                        'source_document_id=% referencia entity id=% que não é do tipo document',
                        NEW.id, NEW.source_document_id;
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");
        $this->db->query("
            CREATE TRIGGER trg_relationships_source_is_document
                BEFORE INSERT OR UPDATE ON relationships
                FOR EACH ROW EXECUTE FUNCTION check_source_is_document()
        ");
    }

    public function down(): void
    {
        // Desfaz na ordem: triggers → views → função → tabela

        $this->db->query("DROP TRIGGER IF EXISTS trg_relationships_source_is_document ON relationships");
        $this->db->query("DROP TRIGGER IF EXISTS trg_relationships_updated_at ON relationships");
        $this->db->query("DROP FUNCTION IF EXISTS check_source_is_document()");

        $this->db->query("DROP VIEW IF EXISTS hypothesis_relationships");
        $this->db->query("DROP VIEW IF EXISTS confirmed_relationships");

        $this->db->query("DROP TABLE IF EXISTS relationships");
    }
}
