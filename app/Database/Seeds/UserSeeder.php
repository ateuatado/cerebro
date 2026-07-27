<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $userModel = new UserModel();

        // Gerar senhas aleatórias — exibidas uma única vez no console
        // Nunca fixadas como string literal no código-fonte
        $coordPassword  = bin2hex(random_bytes(8));
        $collabPassword = bin2hex(random_bytes(8));

        echo "\n╔══════════════════════════════════════════════════╗\n";
        echo "║  CREDENCIAIS GERADAS                             ║\n";
        echo "║  Anote antes de fechar o terminal.               ║\n";
        echo "║  Estas senhas NUNCA serão exibidas novamente.    ║\n";
        echo "╠══════════════════════════════════════════════════╣\n";
        echo "║                                                  ║\n";
        printf("║  Coordenador: %-36s ║\n", 'eliane@exemplo.com');
        printf("║  Senha:       %-36s ║\n", $coordPassword);
        echo "║                                                  ║\n";
        printf("║  Colaborador: %-36s ║\n", 'aluno@exemplo.com');
        printf("║  Senha:       %-36s ║\n", $collabPassword);
        echo "║                                                  ║\n";
        echo "╚══════════════════════════════════════════════════╝\n\n";

        // Coordenador (pode confirmar hipóteses)
        $userModel->insert([
            'name'          => 'Eliane Furoni',
            'email'         => 'eliane@exemplo.com',
            'password_hash' => password_hash($coordPassword, PASSWORD_DEFAULT),
            'role'          => 'coordenador',
        ]);

        // Colaborador de exemplo
        $userModel->insert([
            'name'          => 'Aluno Pesquisador',
            'email'         => 'aluno@exemplo.com',
            'password_hash' => password_hash($collabPassword, PASSWORD_DEFAULT),
            'role'          => 'colaborador',
        ]);

        echo "2 usuários inseridos.\n";
    }
}
