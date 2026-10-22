<?php

use App\Services\ExploratoryGraphService;
use CodeIgniter\Test\CIUnitTestCase;

final class ExploratoryGraphTest extends CIUnitTestCase
{
    public function testHiddenHubLeavesNeighboursVisibleAndCanBeRestored(): void
    {
        $entities = [
            ['id' => '1', 'type' => 'person', 'graph_hidden' => 't'],
            ['id' => '2', 'type' => 'person', 'graph_hidden' => 'f'],
            ['id' => '3', 'type' => 'person', 'graph_hidden' => false],
        ];
        $edges = [
            ['id' => 1, 'source_entity_id' => 1, 'target_entity_id' => 2, 'relationship_type' => 'conheceu'],
            ['id' => 2, 'source_entity_id' => 2, 'target_entity_id' => 3, 'relationship_type' => 'conheceu'],
        ];
        $service = new ExploratoryGraphService();
        $this->assertSame(['2', '3'], array_column($service->entities($entities), 'id'));
        $this->assertSame([2], array_column($service->relationships($entities, $edges), 'id'));
        $this->assertCount(3, $entities);
        $this->assertCount(2, $edges);
        $entities[0]['graph_hidden'] = 'f';
        $this->assertCount(3, $service->entities($entities));
        $this->assertCount(2, $service->relationships($entities, $edges));
    }

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
