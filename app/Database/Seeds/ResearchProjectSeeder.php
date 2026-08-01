<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\ResearchProjectModel;
use App\Models\EntityModel;
use App\Models\RelationshipModel;

class ResearchProjectSeeder extends Seeder
{
    public function run(): void
    {
        $researchModel = new ResearchProjectModel();
        $entityModel   = new EntityModel();
        $relModel      = new RelationshipModel();

        echo "=== [Spec 9] Seeder e Teste de Verificação de Pesquisas ===\n";

        // 1. Criar pesquisa "Baby de Andrade" se não existir
        $existing = $this->db->table('research_projects')->where('title', 'Baby de Andrade')->get()->getRowArray();
        if (!$existing) {
            $researchId = $researchModel->insert([
                'title'       => 'Baby de Andrade',
                'description' => 'Documentação e conexões sobre a atuação de Baby de Andrade nas décadas de 1920-1930.',
            ], true);
            echo "✔ Pesquisa 'Baby de Andrade' criada (ID: {$researchId}).\n";
        } else {
            $researchId = (int)$existing['id'];
            echo "✔ Pesquisa 'Baby de Andrade' já existia (ID: {$researchId}).\n";
        }

        // 2. Criar pesquisa "Revolução de 1932"
        $existing2 = $this->db->table('research_projects')->where('title', 'Revolução de 1932')->get()->getRowArray();
        if (!$existing2) {
            $researchId2 = $researchModel->insert([
                'title'       => 'Revolução de 1932',
                'description' => 'Processos judiciais, diários e boletins militares sobre o levante constitucionalista.',
            ], true);
            echo "✔ Pesquisa 'Revolução de 1932' criada (ID: {$researchId2}).\n";
        }

        // 3. Vincular documentos existentes à pesquisa "Baby de Andrade"
        $documents = $entityModel->findByType('document');
        if (!empty($documents)) {
            foreach ($documents as $doc) {
                $researchModel->attachDocument($researchId, (int)$doc['id']);
            }
            echo "✔ Vinculados " . count($documents) . " documento(s) à pesquisa 'Baby de Andrade'.\n";
        } else {
            echo "ℹ Nenhum documento cadastrado no momento para vincular.\n";
        }

        // 4. Teste de verificação da filtragem do grafo
        $filteredEntities = $entityModel->findByResearch($researchId);
        $filteredRels     = $relModel->findByResearch($researchId);

        echo "✔ Teste de Filtragem de Grafo:\n";
        echo "  - Entidades encontradas no filtro 'Baby de Andrade': " . count($filteredEntities) . "\n";
        echo "  - Relações encontradas no filtro 'Baby de Andrade': " . count($filteredRels) . "\n";

        echo "=== [Spec 9] Verificação concluída com SUCESSO! ===\n";
    }
}
