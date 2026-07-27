<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'name',
        'email',
        'password_hash',
        'role',
        'active',
    ];

    /**
     * Busca usuário por email. Retorna o primeiro registro ou null.
     */
    public function findByEmail(string $email): ?array
    {
        $result = $this->where('email', $email)->first();
        return $result ?: null;
    }

    /**
     * Verifica se a senha confere com o hash armazenado.
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Verifica se o usuário está ativo.
     */
    public function isActive(int $id): bool
    {
        $user = $this->find($id);
        return $user && !empty($user['active']);
    }

    /**
     * Verifica se o usuário tem role = 'coordenador'.
     */
    public function isCoordenador(int $id): bool
    {
        $user = $this->find($id);
        return $user && ($user['role'] ?? '') === 'coordenador';
    }
}
