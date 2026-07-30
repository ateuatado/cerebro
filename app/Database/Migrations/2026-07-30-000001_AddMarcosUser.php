<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMarcosUser extends Migration
{
    public function up(): void
    {
        $email = 'marcosantofoto@gmail.com';
        $pass  = 'Lula#Eleito26';
        $hash  = password_hash($pass, PASSWORD_DEFAULT);

        // Verificar se usuário já existe
        $row = $this->db->table('users')->where('email', $email)->get()->getRowArray();

        if ($row) {
            $this->db->table('users')->where('email', $email)->update([
                'name'          => 'Marcos Santo',
                'password_hash' => $hash,
                'role'          => 'coordenador',
                'active'        => true,
                'updated_at'    => date('Y-m-d H:i:s'),
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
