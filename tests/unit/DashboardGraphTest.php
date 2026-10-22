<?php

use App\Controllers\Home;
use App\Models\EntityModel;
use App\Models\RelationshipModel;
use CodeIgniter\Test\CIUnitTestCase;

final class DashboardGraphTest extends CIUnitTestCase
{
    public function testDashboardUsesAllEdgesWhileRecentListKeepsEight(): void
    {
        $entities = [
            ['id' => 1, 'name' => 'O PARAFUSO', 'type' => 'document', 'status' => 'hypothesis'],
            ['id' => 2, 'name' => 'Pessoa', 'type' => 'person', 'status' => 'hypothesis'],
            ['id' => 3, 'name' => 'Página', 'type' => 'document', 'status' => 'confirmed'],
        ];
        $relations = [];
        for ($id = 1; $id <= 12; $id++) {
            $relations[] = ['id' => $id, 'source_entity_id' => 1, 'target_entity_id' => 2,
                'source_document_id' => 3, 'relationship_type' => 'publicou',
                'direction' => 'directed', 'confidence' => 0.8, 'status' => 'hypothesis'];
        }
        $entityModel = $this->getMockBuilder(EntityModel::class)->disableOriginalConstructor()
            ->onlyMethods(['findAllRaw', 'findAll', 'find'])->addMethods(['orderBy'])->getMock();
        $entityModel->method('findAllRaw')->willReturn($entities);
        $entityModel->method('orderBy')->willReturnSelf();
        $entityModel->method('findAll')->with(8)->willReturn($entities);
        $entityModel->method('find')->willReturnCallback(static fn($id) => $entities[$id - 1]);
        $relModel = $this->getMockBuilder(RelationshipModel::class)->disableOriginalConstructor()
            ->onlyMethods(['findGraphEdges', 'findAll'])->addMethods(['orderBy'])->getMock();
        $relModel->expects($this->once())->method('findGraphEdges')->willReturn($relations);
        $relModel->method('orderBy')->willReturnSelf();
        $relModel->expects($this->once())->method('findAll')->with(8)->willReturn(array_slice($relations, -8));
        ob_start();
        $html = (new Home($entityModel, $relModel))->index();
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $raw = $xpath->query('//*[@id="graph-data"]')->item(0);
        $this->assertNotNull($raw);
        $data = json_decode($raw->textContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(12, $data['relationships']);
        $this->assertSame(range(1, 12), array_column($data['relationships'], 'id'));
        $this->assertCount(3, $data['entities']);
        $this->assertSame(8, $xpath->query('//ul[@aria-label="Relações recentes"]/li')->length);
    }
}
