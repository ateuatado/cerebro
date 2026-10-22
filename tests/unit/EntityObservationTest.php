<?php

use App\Services\EntityObservationService;
use App\Services\EntityIdentityService;
use CodeIgniter\Test\CIUnitTestCase;

final class EntityObservationTest extends CIUnitTestCase
{
    public function testFingerprintIgnoresObjectKeyOrderButKeepsConflicts(): void
    {
        $a = EntityObservationService::fingerprint('location', ['endereco' => ['rua' => 'A', 'numero' => 1]], ['fonte' => 'Jornal']);
        $b = EntityObservationService::fingerprint('location', ['endereco' => ['numero' => 1, 'rua' => 'A']], ['fonte' => 'Jornal']);
        $c = EntityObservationService::fingerprint('location', ['endereco' => ['numero' => 2, 'rua' => 'A']], ['fonte' => 'Jornal']);
        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertNotSame($a, EntityObservationService::fingerprint('person', ['endereco' => ['rua' => 'A', 'numero' => 1]], ['fonte' => 'Jornal']));
    }

    public function testIdentityPreservesAccentsAndNormalizesWhitespace(): void
    {
        $identity = new EntityIdentityService();
        $this->assertSame('chá do paraná', $identity->normalize("  CHÁ  do\nParaná "));
        $this->assertNotSame($identity->normalize('Chá'), $identity->normalize('Cha'));
    }
}
