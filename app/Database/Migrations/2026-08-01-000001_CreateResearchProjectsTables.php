<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResearchProjectsTables extends Migration
{
    public function up(): void
    {
        // 1. Tabela: research_projects
        $this->db->query("
            CREATE TABLE IF NOT EXISTS research_projects (
                id          SERIAL PRIMARY KEY,
                title       TEXT NOT NULL,
                description TEXT,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_by  INTEGER REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $this->db->query("CREATE INDEX IF NOT EXISTS idx_research_projects_created_by ON research_projects (created_by)");

        // Trigger updated_at para research_projects
        $this->db->query("DROP TRIGGER IF EXISTS trg_research_projects_updated_at ON research_projects");
        $this->db->query("
            CREATE TRIGGER trg_research_projects_updated_at
                BEFORE UPDATE ON research_projects
                FOR EACH ROW EXECUTE FUNCTION update_timestamp()
        ");

        // 2. Tabela pivot: research_documents
        $this->db->query("
            CREATE TABLE IF NOT EXISTS research_documents (
                research_id INTEGER NOT NULL REFERENCES research_projects(id) ON DELETE CASCADE,
                document_id INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (research_id, document_id)
            )
        ");

        $this->db->query("CREATE INDEX IF NOT EXISTS idx_rd_research ON research_documents (research_id)");
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_rd_document ON research_documents (document_id)");
    }

    public function down(): void
    {
        $this->db->query("DROP INDEX IF EXISTS idx_rd_document");
        $this->db->query("DROP INDEX IF EXISTS idx_rd_research");
        $this->db->query("DROP TABLE IF EXISTS research_documents");

        $this->db->query("DROP TRIGGER IF EXISTS trg_research_projects_updated_at ON research_projects");
        $this->db->query("DROP INDEX IF EXISTS idx_research_projects_created_by");
        $this->db->query("DROP TABLE IF EXISTS research_projects");
    }
}
