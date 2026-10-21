<?php

namespace App\Commands;

use App\Models\UserModel;
use App\Services\AuthService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ResetPasswordCommand extends BaseCommand
{
    protected $group = 'Auth';
    protected $name = 'auth:reset-password';
    protected $description = 'Redefine a senha de um usuário sem exibir ou armazenar a senha em texto puro.';

    public function run(array $params)
    {
        $email = CLI::prompt('E-mail', null, 'required|valid_email');
        $user = (new UserModel())->findByEmail($email);
        if (! $user) {
            CLI::error('Usuário não encontrado.');
            return EXIT_ERROR;
        }

        $password = $this->hiddenPrompt('Nova senha');
        $confirmation = $this->hiddenPrompt('Confirme a nova senha');
        if ($password !== $confirmation) {
            CLI::error('As senhas não conferem.');
            return EXIT_ERROR;
        }

        [$ok, $message] = (new AuthService())->resetPasswordByAdmin((int) $user['id'], $password);
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
            if ($state) shell_exec('stty ' . trim($state));
            CLI::write('');
        }
        return $value;
    }
}
