<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\AuthService;

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
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        if ($this->auth->login($email, $password)) {
            return redirect()->to('/');
        }

        session()->setFlashdata('error', 'Email ou senha inválidos.');
        return redirect()->to('/login');
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
