<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Verifica se há sessão ativa. Se não, redireciona para /login.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session('user_id') === null) {
            session()->setFlashdata('error', 'Faça login para acessar esta página.');
            return redirect()->to('/login');
        }
    }

    /**
     * No-op: nada a fazer após o controller.
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada
    }
}
