<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSecureUserLifecycle extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN NOT NULL DEFAULT false");

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id          BIGSERIAL PRIMARY KEY,
                user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token_hash  CHAR(64) NOT NULL UNIQUE,
                expires_at  TIMESTAMPTZ NOT NULL,
                used_at     TIMESTAMPTZ NULL,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);

        $this->db->query("CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_user ON password_reset_tokens (user_id)");
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_expiry ON password_reset_tokens (expires_at)");
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS password_reset_tokens');
        $this->db->query('ALTER TABLE users DROP COLUMN IF EXISTS must_change_password');
    }
}
