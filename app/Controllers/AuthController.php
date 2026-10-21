<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\AuthService;
use App\Models\UserModel;

class AuthController extends BaseController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    /**
     * GET /login — Exibe o formulário de login.
     * Se já estiver logado, redireciona para /.
     */
    public function loginForm()
    {
        if ($this->auth->isLoggedIn()) {
            return redirect()->to('/');
        }

        return view('auth/login');
    }

    /**
     * POST /login — Processa as credenciais.
     * Sucesso → redirect /. Falha → redirect /login com flash error.
     */
    public function loginAction()
    {
        $email    = (string) $this->request->getPost('email');
        $password = (string) $this->request->getPost('password');

        if ($this->auth->login($email, $password)) {
            return redirect()->to('/');
        }

        session()->setFlashdata('error', 'Email ou senha inválidos.');
        return redirect()->to('/login');
    }

    public function forgotForm()
    {
        return view('auth/forgot_password');
    }

    public function forgotAction()
    {
        $this->auth->requestPasswordReset((string) $this->request->getPost('email'));
        session()->setFlashdata('success', 'Se o e-mail estiver cadastrado, enviaremos instruções para redefinir a senha.');
        return redirect()->to('/senha/esqueci');
    }

    public function changePasswordForm()
    {
        return view('auth/change_password');
    }

    public function changePasswordAction()
    {
        [$ok, $message] = $this->auth->changePassword(
            (int) session('user_id'),
            (string) $this->request->getPost('current_password'),
            (string) $this->request->getPost('password'),
            (string) $this->request->getPost('password_confirmation')
        );
        session()->setFlashdata($ok ? 'success' : 'error', $message);
        return redirect()->to($ok ? '/' : '/senha');
    }

    public function resetForm(string $token)
    {
        return view('auth/reset_password', ['token' => $token]);
    }

    public function resetAction(string $token)
    {
        [$ok, $message] = $this->auth->resetPassword(
            $token,
            (string) $this->request->getPost('password'),
            (string) $this->request->getPost('password_confirmation')
        );
        session()->setFlashdata($ok ? 'success' : 'error', $message);
        return redirect()->to($ok ? '/login' : '/senha/redefinir/' . $token);
    }

    public function users()
    {
        if (! $this->auth->canConfirm()) {
            return $this->response->setStatusCode(403)->setBody('Acesso restrito ao coordenador.');
        }
        return view('auth/users', ['users' => (new UserModel())->orderBy('name')->findAll()]);
    }

    public function createUserForm()
    {
        if (! $this->auth->canConfirm()) {
            return $this->response->setStatusCode(403)->setBody('Acesso restrito ao coordenador.');
        }
        return view('auth/create_user');
    }

    public function createUserAction()
    {
        if (! $this->auth->canConfirm()) {
            return $this->response->setStatusCode(403)->setBody('Acesso restrito ao coordenador.');
        }
        [$ok, $message] = $this->auth->createUser(
            (string) $this->request->getPost('name'),
            (string) $this->request->getPost('email'),
            (string) $this->request->getPost('password'),
            (string) $this->request->getPost('role')
        );
        session()->setFlashdata($ok ? 'success' : 'error', $message);
        return redirect()->to($ok ? '/usuarios' : '/usuarios/novo');
    }

    /**
     * GET /logout — Destrói a sessão e redireciona para /login.
     */
    public function logout()
    {
        $this->auth->logout();
        return redirect()->to('/login');
    }

    /**
     * POST /teste-autorizacao-coordenador — Scaffolding de teste.
     *
     * Verifica AuthService::canConfirm() e retorna 200 ou 403.
     * NÃO altera nenhum dado. Este endpoint existe apenas para validar
     * a lógica de autorização da Spec 2 e será substituído pelo endpoint
     * real de confirmação quando a spec de revisão for implementada.
     */
    public function testeAutorizacao()
    {
        if ($this->auth->canConfirm()) {
            return $this->response->setJSON(['autorizado' => true])->setStatusCode(200);
        }

        return $this->response->setJSON([
            'autorizado' => false,
            'motivo'     => 'requer role coordenador',
        ])->setStatusCode(403);
    }
}
