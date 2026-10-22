<?php

use App\Services\EntityObservationService;
use PHPUnit\Framework\TestCase;
use Config\Database;

/** Teste opt-in: schema isolado e transação revertida; não altera o acervo. */
final class EntityObservationPostgresTest extends TestCase
{
    private $pg;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CEREBRO_PG_TESTS') !== '1') {
            $this->markTestSkipped('Ative CEREBRO_PG_TESTS=1 para testar PostgreSQL em schema isolado.');
        }
        $config = config('Database');
        if ($config->tests['DBDriver'] !== 'Postgre' || $config->tests['DBPrefix'] !== '') {
            $this->markTestSkipped('Configure database.tests para PostgreSQL dedicado, sem prefixo.');
        }
        $this->pg = Database::connect();
        $this->assertSame('Postgre', $this->pg->DBDriver);
        $this->pg->transException(true);
        $this->pg->transBegin();
        $schema = 'test_observations_' . bin2hex(random_bytes(6));
        $this->pg->query('CREATE SCHEMA ' . $schema);
        $this->pg->query('SET LOCAL search_path TO ' . $schema);
        $forge = Database::forge();
        require_once APPPATH . 'Database/Migrations/2026-07-27-000001_CreateEntitiesTable.php';
        require_once APPPATH . 'Database/Migrations/2026-07-27-000002_CreateRelationshipsTable.php';
        require_once APPPATH . 'Database/Migrations/2026-10-22-000001_CreateEntityObservations.php';
        (new \App\Database\Migrations\CreateEntitiesTable($forge))->up();
        (new \App\Database\Migrations\CreateRelationshipsTable($forge))->up();
        (new \App\Database\Migrations\CreateEntityObservations($forge))->up();
    }

    protected function tearDown(): void
    {
        if ($this->pg) {
            $this->pg->transRollback();
        }
        parent::tearDown();
    }

    public function testMultipleSourcesAndTypesKeepOneIdentityAndAllEvidence(): void
    {
        $this->pg->query("INSERT INTO entities (name,type) VALUES ('Página 1','document'),('Página 2','document')");
        $docs = $this->pg->table('entities')->orderBy('id')->get()->getResultArray();
        $service = new EntityObservationService();
        $first = $service->record(['name' => 'Chá do Paraná', 'type' => 'location', 'attributes' => ['descricao' => 'Anúncio A']], (int)$docs[0]['id'], 'Página 1', null);
        $this->pg->table('entities')->where('id', $first['id'])->update(['status' => 'confirmed', 'validated_by' => '1']);
        $second = $service->record(['name' => ' CHÁ  do Paraná ', 'type' => 'event', 'attributes' => ['descricao' => 'Anúncio B']], (int)$docs[1]['id'], 'Página 2', null);
        $this->assertSame($first['id'], $second['id']);
        $this->assertFalse($second['created']);
        $service->record(['name' => 'Chá do Paraná', 'type' => 'event', 'attributes' => ['descricao' => 'Anúncio B']], (int)$docs[1]['id'], 'Página 2', null);
        $this->assertSame(2, $this->pg->table('entity_observations')->countAllResults());
        $row = $this->pg->table('entities')->where('id', $first['id'])->get()->getRowArray();
        $this->assertSame('confirmed', $row['status']);
        $this->assertSame('Anúncio A', json_decode($row['attributes'], true)['descricao']);
        $this->assertSame('location', $row['type']);
        $this->assertSame(2, $this->pg->table('entity_observations')->where('status', 'hypothesis')->countAllResults());
        $document = $service->record(['name' => 'Chá do Paraná', 'type' => 'document'], (int)$docs[0]['id'], 'Página 1', null);
        $this->assertNotSame($first['id'], $document['id']);
        $this->pg->table('entities')->where('id', $docs[0]['id'])->delete();
        $this->assertSame(1, $this->pg->table('entity_observations')->countAllResults());
    }

    public function testGraphReusesNodesButKeepsRelationshipsFromDifferentSources(): void
    {
        $this->pg->query("INSERT INTO entities (name,type) VALUES ('Página 1','document'),('Página 2','document')");
        $docs = $this->pg->table('entities')->orderBy('id')->get()->getResultArray();
        $entities = [
            ['name' => 'Chá do Paraná', 'type' => 'location', 'attributes' => ['descricao' => 'Anúncio']],
            ['name' => 'Dr. Petinio', 'type' => 'person'],
        ];
        $relations = [['source_name' => 'Dr. Petinio', 'target_name' => 'Chá do Paraná',
            'relationship_type' => 'recomendou', 'excerpt' => 'Recomenda o Chá do Paraná']];
        $service = new \App\Services\DocumentExtractionService();
        $first = $service->persistGraphData((int)$docs[0]['id'], 'Página 1', $entities, $relations, null);
        $entities[0]['type'] = 'event';
        $second = $service->persistGraphData((int)$docs[1]['id'], 'Página 2', $entities, $relations, null);
        $this->assertSame($first['nameToIdMap'], $second['nameToIdMap']);
        $this->assertSame(0, $second['newEntities']);
        $this->assertSame(2, $this->pg->table('relationships')->countAllResults());
        $this->assertSame(4, $this->pg->table('entity_observations')->countAllResults());
        $repeat = $service->persistGraphData((int)$docs[1]['id'], 'Página 2', $entities, $relations, null);
        $this->assertSame(0, $repeat['newRels']);
        $this->assertSame(4, $this->pg->table('entity_observations')->countAllResults());
        $this->pg->query("INSERT INTO entities (name,type) VALUES ('Identidade revisada','location')");
        $target = (int)$this->pg->insertID();
        (new EntityObservationService())->transfer($first['nameToIdMap']['chá do paraná'], $target);
        $this->assertSame(2, $this->pg->table('entity_observations')->where('entity_id', $target)->countAllResults());
    }
}
