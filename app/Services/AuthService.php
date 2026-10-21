<?php

namespace App\Services;

use App\Models\UserModel;
use Config\Database;

class AuthService
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Autentica um usuário por email e senha.
     *
     * Retorna false em qualquer caminho de falha (email inexistente,
     * senha incorreta, conta inativa) — mesma mensagem genérica em
     * todos os casos, sem revelar qual condição falhou (anti-enumeração).
     */
    public function login(string $email, string $password): bool
    {
        $user = $this->userModel->findByEmail($email);

        // Email não encontrado → false (sem revelar)
        if ($user === null) {
            return false;
        }

        // Senha incorreta → false (sem revelar)
        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            return false;
        }

        // Conta inativa → false (sem revelar)
        // PostgreSQL retorna BOOLEAN como string 't'/'f'; tratar ambos
        $active = $user['active'];
        if ($active === false || $active === 'f' || $active === 'false' || $active === 0 || $active === '0') {
            return false;
        }

        // Sucesso: popular sessão
        session()->set([
            'user_id' => $user['id'],
            'email'   => $user['email'],
            'name'    => $user['name'],
            'role'    => $user['role'],
            'must_change_password' => $this->isTruthyValue($user['must_change_password'] ?? false),
        ]);
        session()->regenerate(true);

        return true;
    }

    /**
     * Destrói a sessão atual.
     */
    public function logout(): void
    {
        // session_destroy() pode falhar em CLI se a sessão não foi
        // inicializada pelo PHP. Removemos as chaves manualmente
        // como fallback.
        try {
            session()->destroy();
        } catch (\ErrorException $e) {
            // Sessão não inicializada — limpar manualmente
            session()->remove(['user_id', 'email', 'name', 'role', 'must_change_password']);
        }
    }

    /**
     * Retorna dados do usuário logado ou null se não houver sessão ativa.
     */
    public function currentUser(): ?array
    {
        $userId = session('user_id');

        if ($userId === null) {
            return null;
        }

        return [
            'user_id' => (int) $userId,
            'email'   => session('email'),
            'name'    => session('name'),
            'role'    => session('role'),
            'must_change_password' => $this->isTruthyValue(session('must_change_password')),
        ];
    }

    /**
     * Verifica se há um usuário logado.
     */
    public function isLoggedIn(): bool
    {
        return session('user_id') !== null;
    }

    /**
     * Verifica se o usuário logado é coordenador.
     */
    public function isCoordenador(): bool
    {
        return session('role') === 'coordenador';
    }

    /**
     * O usuário logado pode confirmar hipótese → fato?
     * Alias para isCoordenador() — extensível no futuro.
     */
    public function canConfirm(): bool
    {
        return $this->isCoordenador();
    }

    public function passwordMeetsPolicy(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/\d/', $password) === 1;
    }

    public function changePassword(int $userId, string $current, string $new, string $confirmation): array
    {
        $user = $this->userModel->find($userId);
        if (! $user || ! $this->userModel->verifyPassword($current, $user['password_hash'])) {
            return [false, 'A senha atual está incorreta.'];
        }
        if ($new !== $confirmation) {
            return [false, 'A confirmação da nova senha não confere.'];
        }
        if (! $this->passwordMeetsPolicy($new)) {
            return [false, 'A nova senha deve ter pelo menos 12 caracteres, uma maiúscula, uma minúscula e um número.'];
        }
        if ($this->userModel->verifyPassword($new, $user['password_hash'])) {
            return [false, 'A nova senha deve ser diferente da senha atual.'];
        }

        $this->userModel->update($userId, [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
            'must_change_password' => false,
        ]);
        session()->set('must_change_password', false);
        return [true, 'Senha alterada com sucesso.'];
    }

    public function createUser(string $name, string $email, string $password, string $role = 'colaborador'): array
    {
        $name = trim($name);
        $email = mb_strtolower(trim($email));
        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Informe nome e e-mail válidos.'];
        }
        if (! in_array($role, ['coordenador', 'colaborador'], true)) {
            return [false, 'Perfil inválido.'];
        }
        if (! $this->passwordMeetsPolicy($password)) {
            return [false, 'A senha deve ter pelo menos 12 caracteres, uma maiúscula, uma minúscula e um número.'];
        }
        if ($this->userModel->findByEmail($email)) {
            return [false, 'Já existe um usuário com este e-mail.'];
        }

        $this->userModel->insert([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'active' => true,
            'must_change_password' => true,
        ]);
        return [true, 'Usuário criado. A pessoa deverá trocar a senha no primeiro acesso.'];
    }

    public function resetPasswordByAdmin(int $userId, string $password): array
    {
        if (! $this->passwordMeetsPolicy($password)) {
            return [false, 'A senha deve ter pelo menos 12 caracteres, uma maiúscula, uma minúscula e um número.'];
        }
        if (! $this->userModel->find($userId)) {
            return [false, 'Usuário não encontrado.'];
        }
        $this->userModel->update($userId, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => true,
        ]);
        return [true, 'Senha redefinida. A troca será obrigatória no próximo acesso.'];
    }

    public function requestPasswordReset(string $email): void
    {
        $db = Database::connect();
        $user = $this->userModel->findByEmail($email);
        if (! $user || ! $this->isActiveValue($user['active'])) {
            return;
        }

        $rawToken = bin2hex(random_bytes(32));
        $db->table('password_reset_tokens')
            ->where('user_id', $user['id'])
            ->where('used_at IS NULL', null, false)
            ->delete();
        $db->table('password_reset_tokens')->insert([
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => date('c', time() + 3600),
        ]);

        $emailService = service('email');
        if ((string) config('Email')->fromEmail === '') {
            log_message('warning', 'Recuperação de senha solicitada, mas EMAIL_FROM/SMTP não está configurado.');
            return;
        }
        $url = base_url('senha/redefinir/' . $rawToken);
        $emailService->setTo($user['email']);
        $emailService->setSubject('Redefinição de senha — Cerebro');
        $emailService->setMessage("Olá, {$user['name']}.\n\nAcesse este link para definir uma nova senha (válido por 1 hora):\n{$url}\n\nSe você não solicitou isso, ignore esta mensagem.");
        if (! $emailService->send()) {
            log_message('error', 'Falha ao enviar e-mail de recuperação para o usuário ' . $user['id']);
        }
    }

    public function resetPassword(string $rawToken, string $new, string $confirmation): array
    {
        $db = Database::connect();
        $row = $db->table('password_reset_tokens t')
            ->select('t.id AS token_id, t.user_id, u.password_hash')
            ->join('users u', 'u.id = t.user_id')
            ->where('t.token_hash', hash('sha256', $rawToken))
            ->where('t.used_at IS NULL', null, false)
            ->where('t.expires_at > NOW()', null, false)
            ->get()->getRowArray();
        if (! $row) {
            return [false, 'O link é inválido ou expirou.'];
        }
        if ($new !== $confirmation || ! $this->passwordMeetsPolicy($new)) {
            return [false, 'A nova senha deve coincidir e ter pelo menos 12 caracteres, uma maiúscula, uma minúscula e um número.'];
        }
        if ($this->userModel->verifyPassword($new, $row['password_hash'])) {
            return [false, 'A nova senha deve ser diferente da senha anterior.'];
        }

        $db->transStart();
        $this->userModel->update((int) $row['user_id'], [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
            'must_change_password' => false,
        ]);
        $db->table('password_reset_tokens')->where('id', $row['token_id'])->update(['used_at' => date('c')]);
        $db->transComplete();
        return $db->transStatus()
            ? [true, 'Senha redefinida com sucesso.']
            : [false, 'Não foi possível salvar a nova senha.'];
    }

    private function isActiveValue($active): bool
    {
        return ! in_array($active, [false, 'f', 'false', 0, '0'], true);
    }

    private function isTruthyValue($value): bool
    {
        return ! in_array($value, [false, 'f', 'false', 0, '0', null], true);
    }
}
