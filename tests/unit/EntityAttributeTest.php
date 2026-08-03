<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EntityAttributeTest extends CIUnitTestCase
{
    public function testJsonbAttributeModification(): void
    {
        $initialJson = json_encode([
            'status' => 'falecido',
            'nacionalidade' => 'italiano'
        ], JSON_UNESCAPED_UNICODE);

        $attrs = json_decode($initialJson, true);
        $this->assertArrayHasKey('status', $attrs);
        $this->assertArrayHasKey('nacionalidade', $attrs);

        // Adicionar novo atributo
        $attrs['ocupacao'] = 'Comerciante';
        $updatedJson = json_encode($attrs, JSON_UNESCAPED_UNICODE);

        $decodedUpdated = json_decode($updatedJson, true);
        $this->assertEquals('Comerciante', $decodedUpdated['ocupacao']);

        // Remover atributo
        unset($decodedUpdated['nacionalidade']);
        $finalJson = json_encode($decodedUpdated, JSON_UNESCAPED_UNICODE);

        $decodedFinal = json_decode($finalJson, true);
        $this->assertArrayNotHasKey('nacionalidade', $decodedFinal);
        $this->assertEquals('falecido', $decodedFinal['status']);
        $this->assertEquals('Comerciante', $decodedFinal['ocupacao']);
    }
}
