<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        // 3.1 Tabela: users
        $this->db->query("
            CREATE TABLE users (
                id              SERIAL PRIMARY KEY,
                name            TEXT NOT NULL,
                email           TEXT NOT NULL UNIQUE,
                password_hash   TEXT NOT NULL,
                role            TEXT NOT NULL DEFAULT 'colaborador'
                                CHECK (role IN ('coordenador', 'colaborador')),
                active          BOOLEAN NOT NULL DEFAULT true,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        // Índices
        $this->db->query("CREATE INDEX idx_users_email  ON users (email)");
        $this->db->query("CREATE INDEX idx_users_role   ON users (role)");
        $this->db->query("CREATE INDEX idx_users_active ON users (active)");

        // 3.4 Trigger updated_at (reusa função da Spec 1)
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
            CREATE TRIGGER trg_users_updated_at
                BEFORE UPDATE ON users
                FOR EACH ROW EXECUTE FUNCTION update_timestamp()
        ");
    }

    public function down(): void
    {
        $this->db->query("DROP TRIGGER IF EXISTS trg_users_updated_at ON users");
        $this->db->query("DROP INDEX IF EXISTS idx_users_active");
        $this->db->query("DROP INDEX IF EXISTS idx_users_role");
        $this->db->query("DROP INDEX IF EXISTS idx_users_email");
        $this->db->query("DROP TABLE IF EXISTS users");
    }
}
