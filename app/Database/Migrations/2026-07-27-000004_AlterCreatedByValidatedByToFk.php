<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterCreatedByValidatedByToFk extends Migration
{
    public function up(): void
    {
        // === entities ===

        // Views da Spec 1 dependem das colunas — dropar para poder alterar o tipo
        $this->db->query("DROP VIEW IF EXISTS hypothesis_entities");
        $this->db->query("DROP VIEW IF EXISTS confirmed_entities");
        $this->db->query("DROP VIEW IF EXISTS entity_documents");
        $this->db->query("DROP VIEW IF EXISTS entity_events");
        $this->db->query("DROP VIEW IF EXISTS entity_locations");
        $this->db->query("DROP VIEW IF EXISTS entity_persons");
        $this->db->query("DROP VIEW IF EXISTS hypothesis_relationships");
        $this->db->query("DROP VIEW IF EXISTS confirmed_relationships");

        // Passo 1: remover constraint que referencia validated_by
        $this->db->query("ALTER TABLE entities DROP CONSTRAINT IF EXISTS confirmed_requires_validation");

        // Passo 2: converter colunas de TEXT para INTEGER
        $this->db->query("ALTER TABLE entities ALTER COLUMN created_by TYPE INTEGER USING (created_by::INTEGER)");
        $this->db->query("ALTER TABLE entities ALTER COLUMN validated_by TYPE INTEGER USING (validated_by::INTEGER)");

        // Passo 3: adicionar FKs
        $this->db->query("ALTER TABLE entities ADD CONSTRAINT fk_entities_created_by FOREIGN KEY (created_by) REFERENCES users(id)");
        $this->db->query("ALTER TABLE entities ADD CONSTRAINT fk_entities_validated_by FOREIGN KEY (validated_by) REFERENCES users(id)");

        // Passo 4: recriar constraint do Princípio III
        $this->db->query("ALTER TABLE entities ADD CONSTRAINT confirmed_requires_validation CHECK (status <> 'confirmed' OR validated_by IS NOT NULL)");

        // === relationships ===

        $this->db->query("ALTER TABLE relationships DROP CONSTRAINT IF EXISTS confirmed_requires_validation");

        $this->db->query("ALTER TABLE relationships ALTER COLUMN created_by TYPE INTEGER USING (created_by::INTEGER)");
        $this->db->query("ALTER TABLE relationships ALTER COLUMN validated_by TYPE INTEGER USING (validated_by::INTEGER)");

        $this->db->query("ALTER TABLE relationships ADD CONSTRAINT fk_relationships_created_by FOREIGN KEY (created_by) REFERENCES users(id)");
        $this->db->query("ALTER TABLE relationships ADD CONSTRAINT fk_relationships_validated_by FOREIGN KEY (validated_by) REFERENCES users(id)");

        $this->db->query("ALTER TABLE relationships ADD CONSTRAINT confirmed_requires_validation CHECK (status <> 'confirmed' OR validated_by IS NOT NULL)");

        // Recriar views da Spec 1
        $this->db->query("CREATE VIEW entity_persons AS SELECT * FROM entities WHERE type = 'person'");
        $this->db->query("CREATE VIEW entity_locations AS SELECT * FROM entities WHERE type = 'location'");
        $this->db->query("CREATE VIEW entity_events AS SELECT * FROM entities WHERE type = 'event'");
        $this->db->query("CREATE VIEW entity_documents AS SELECT * FROM entities WHERE type = 'document'");
        $this->db->query("CREATE VIEW confirmed_entities AS SELECT * FROM entities WHERE status = 'confirmed'");
        $this->db->query("CREATE VIEW hypothesis_entities AS SELECT * FROM entities WHERE status = 'hypothesis'");
        $this->db->query("CREATE VIEW confirmed_relationships AS SELECT * FROM relationships WHERE status = 'confirmed'");
        $this->db->query("CREATE VIEW hypothesis_relationships AS SELECT * FROM relationships WHERE status = 'hypothesis'");
    }

    public function down(): void
    {
        // Dropar views para poder alterar colunas
        $this->db->query("DROP VIEW IF EXISTS hypothesis_relationships");
        $this->db->query("DROP VIEW IF EXISTS confirmed_relationships");
        $this->db->query("DROP VIEW IF EXISTS hypothesis_entities");
        $this->db->query("DROP VIEW IF EXISTS confirmed_entities");
        $this->db->query("DROP VIEW IF EXISTS entity_documents");
        $this->db->query("DROP VIEW IF EXISTS entity_events");
        $this->db->query("DROP VIEW IF EXISTS entity_locations");
        $this->db->query("DROP VIEW IF EXISTS entity_persons");

        // === entities ===
        $this->db->query("ALTER TABLE entities DROP CONSTRAINT IF EXISTS confirmed_requires_validation");
        $this->db->query("ALTER TABLE entities DROP CONSTRAINT IF EXISTS fk_entities_validated_by");
        $this->db->query("ALTER TABLE entities DROP CONSTRAINT IF EXISTS fk_entities_created_by");
        $this->db->query("ALTER TABLE entities ALTER COLUMN created_by TYPE TEXT USING (created_by::TEXT)");
        $this->db->query("ALTER TABLE entities ALTER COLUMN validated_by TYPE TEXT USING (validated_by::TEXT)");
        $this->db->query("ALTER TABLE entities ADD CONSTRAINT confirmed_requires_validation CHECK (status <> 'confirmed' OR validated_by IS NOT NULL)");

        // === relationships ===
        $this->db->query("ALTER TABLE relationships DROP CONSTRAINT IF EXISTS confirmed_requires_validation");
        $this->db->query("ALTER TABLE relationships DROP CONSTRAINT IF EXISTS fk_relationships_validated_by");
        $this->db->query("ALTER TABLE relationships DROP CONSTRAINT IF EXISTS fk_relationships_created_by");
        $this->db->query("ALTER TABLE relationships ALTER COLUMN created_by TYPE TEXT USING (created_by::TEXT)");
        $this->db->query("ALTER TABLE relationships ALTER COLUMN validated_by TYPE TEXT USING (validated_by::TEXT)");
        $this->db->query("ALTER TABLE relationships ADD CONSTRAINT confirmed_requires_validation CHECK (status <> 'confirmed' OR validated_by IS NOT NULL)");

        // Recriar views da Spec 1
        $this->db->query("CREATE VIEW entity_persons AS SELECT * FROM entities WHERE type = 'person'");
        $this->db->query("CREATE VIEW entity_locations AS SELECT * FROM entities WHERE type = 'location'");
        $this->db->query("CREATE VIEW entity_events AS SELECT * FROM entities WHERE type = 'event'");
        $this->db->query("CREATE VIEW entity_documents AS SELECT * FROM entities WHERE type = 'document'");
        $this->db->query("CREATE VIEW confirmed_entities AS SELECT * FROM entities WHERE status = 'confirmed'");
        $this->db->query("CREATE VIEW hypothesis_entities AS SELECT * FROM entities WHERE status = 'hypothesis'");
        $this->db->query("CREATE VIEW confirmed_relationships AS SELECT * FROM relationships WHERE status = 'confirmed'");
        $this->db->query("CREATE VIEW hypothesis_relationships AS SELECT * FROM relationships WHERE status = 'hypothesis'");
    }
}
