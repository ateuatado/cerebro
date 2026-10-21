<?php

use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AuthServiceTest extends CIUnitTestCase
{
    public function testPasswordPolicyRejectsWeakPasswords(): void
    {
        $auth = new AuthService();

        $this->assertFalse($auth->passwordMeetsPolicy('Curta1'));
        $this->assertFalse($auth->passwordMeetsPolicy('senha-com-12-caracteres'));
    }

    public function testPasswordPolicyAcceptsStrongPassword(): void
    {
        $this->assertTrue((new AuthService())->passwordMeetsPolicy('CerebroSeguro2026'));
    }
}
