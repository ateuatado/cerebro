<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMarcosUser extends Migration
{
    public function up(): void
    {
        $email = trim((string) (getenv('CEREBRO_BOOTSTRAP_USER_EMAIL') ?: ''));
        $pass  = (string) (getenv('CEREBRO_BOOTSTRAP_USER_PASSWORD') ?: '');

        // A migration nunca deve conter ou redefinir uma senha fixa.
        // O bootstrap só ocorre quando as duas variáveis são fornecidas
        // explicitamente no ambiente da instalação.
        if ($email === '' || $pass === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);

        // Verificar se usuário já existe
        $row = $this->db->table('users')->where('email', $email)->get()->getRowArray();

        if ($row) {
            $this->db->table('users')->where('email', $email)->update([
                'name'       => 'Marcos Santo',
                'role'       => 'coordenador',
                'active'     => true,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->db->table('users')->insert([
                'name'          => 'Marcos Santo',
                'email'         => $email,
                'password_hash' => $hash,
                'role'          => 'coordenador',
                'active'        => true,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('users')->where('email', 'marcosantofoto@gmail.com')->delete();
    }
}
