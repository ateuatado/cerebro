<?php

namespace App\Services;

use App\Models\UserModel;

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
        if (empty($user['active'])) {
            return false;
        }

        // Sucesso: popular sessão
        session()->set([
            'user_id' => $user['id'],
            'email'   => $user['email'],
            'role'    => $user['role'],
        ]);

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
            session()->remove(['user_id', 'email', 'role']);
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
            'role'    => session('role'),
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
}
