<?php

namespace App\Commands;

use App\Services\AuthService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CreateUserCommand extends BaseCommand
{
    protected $group = 'Auth';
    protected $name = 'auth:create-user';
    protected $description = 'Cria um usuário com senha temporária e troca obrigatória no primeiro acesso.';

    public function run(array $params)
    {
        $name = CLI::prompt('Nome', null, 'required');
        $email = CLI::prompt('E-mail', null, 'required|valid_email');
        $role = CLI::promptByKey('Perfil', ['colaborador' => 'Colaborador', 'coordenador' => 'Coordenador'], 'required');
        $password = $this->hiddenPrompt('Senha temporária');
        $confirmation = $this->hiddenPrompt('Confirme a senha');

        if ($password !== $confirmation) {
            CLI::error('As senhas não conferem.');
            return EXIT_ERROR;
        }

        [$ok, $message] = (new AuthService())->createUser($name, $email, $password, $role);
        $ok ? CLI::write($message, 'green') : CLI::error($message);
        return $ok ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function hiddenPrompt(string $label): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return trim((string) readline($label . ': '));
        }

        $state = shell_exec('stty -g');
        shell_exec('stty -echo');
        try {
            $value = trim((string) readline($label . ': '));
        } finally {
            if ($state) {
                shell_exec('stty ' . trim($state));
            }
            CLI::write('');
        }
        return $value;
    }
}
