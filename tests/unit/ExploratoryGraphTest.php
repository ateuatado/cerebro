<?php

use App\Services\ExploratoryGraphService;
use CodeIgniter\Test\CIUnitTestCase;

final class ExploratoryGraphTest extends CIUnitTestCase
{
    public function testProvenanceIsHiddenButHistoricalConnectionsAndIsolatedNodesArePreserved(): void
    {
        $entities = [
            ['id' => '1', 'type' => 'person'], ['id' => '2', 'type' => 'person'],
            ['id' => '3', 'type' => 'document'], ['id' => '4', 'type' => 'document'],
            ['id' => '5', 'type' => 'location'],
        ];
        $edge = static fn($id, $from, $to, $type, $extra = []) => array_merge([
            'id' => $id, 'source_entity_id' => $from, 'target_entity_id' => $to,
            'relationship_type' => $type, 'source_document_id' => '3',
        ], $extra);
        $relationships = [
            $edge(1, '1', '2', 'conheceu'),
            $edge(2, 3, 1, 'menciona'),
            $edge(3, 1, 3, 'associado_a'),
            $edge(4, 4, 2, 'menciona'),
            $edge(5, 1, 3, '', ['kind' => 'documentary']),
            $edge(6, 1, 4, 'publicou'),
        ];
        $visible = (new ExploratoryGraphService())->relationships($entities, $relationships);
        $this->assertSame([1, 6], array_column($visible, 'id'));
        $this->assertCount(5, $entities);
        $this->assertCount(6, $relationships);
        $this->assertSame('5', $entities[4]['id']);
    }
}
