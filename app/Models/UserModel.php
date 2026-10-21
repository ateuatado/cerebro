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
        'must_change_password',
    ];

    /**
     * Busca usuário por email. Retorna o primeiro registro ou null.
     */
    public function findByEmail(string $email): ?array
    {
        $result = $this->where('email', mb_strtolower(trim($email)))->first();
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
        return $user !== null && ! in_array($user['active'] ?? false, [false, 'f', 'false', 0, '0'], true);
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
